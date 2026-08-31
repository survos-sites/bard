# PLAN.md — survos-sites/bard

Current and near-term work. Append decision records as work progresses.

## Status

Corpus loads itself (`app:load` fetches `oss-textdb.zip` and reads it in place via `zip://`).
Scene-level vectorization is wired but unrun — `app:vectorize` needs `OPENAI_API_KEY`.

**Ask the Scholar is parked** (2026-08-31). The design below stands; the corpus doesn't.
See the decision record before picking it back up.

---

## Decision: bard is not the corpus to prove RAG on (2026-08-31)

Shakespeare's vocabulary is the problem, in both directions.

The archaic diction makes a *demo* look great — of 20 modern terms a user would type,
12 appear literally zero times in all 35,465 speeches (insomnia, suicide, depression,
racism, therapy, anxiety, "mistaken identity", sleepwalking, misogyny, …), while the
concepts are plainly present in 1600s wording: Henry IV Pt 2 3.1 for sleeplessness,
Macbeth 5.1 for guilt, Othello 1.1 for racial othering. Keyword search returns nothing
for any of them; embeddings would. That gap is real and measured.

But the same corpus is close to useless for *testing*. The model already knows Shakespeare
from pretraining, so it will answer "Henry IV 3.1" whether or not the retriever returned
anything. Retrieval success and memorization are indistinguishable, which means recall@k
measured here says almost nothing about whether the pipeline works. Every green result is
confounded.

A test corpus has to be one the model cannot have memorized — the museum assets in
`ssai`/mediary, or any recent private collection. That is also where the real target sits
(millions of items, not 43 plays).

Bard keeps the vectorization code: it's a working reference implementation of structural
chunking, and it stays a reasonable *showcase*. It is not the evaluation harness.

## Known bug before any real vectorize run

Scene token counts, `cl100k_base`, measured over all 945 scenes:

```
min=81  p50=1175  p90=3773  p99=7480  max=24189  mean=1650
```

940 of 945 fit comfortably in an embedding window — scene really is the right unit. But
five exceed `text-embedding-3-small`'s 8191-token input limit and **will fail when
`app:vectorize` runs with a real key**:

| chunk | tokens |
|---|---|
| Rape of Lucrece 1.2 | 24,189 |
| Venus and Adonis 1.1 | 15,984 |
| Love's Labour's Lost 5.2 | 13,377 |
| Winter's Tale 4.4 | 12,309 |
| Hamlet 2.2 | 8,426 |

The two narrative poems aren't scenes at all — a single "chapter" holding the whole poem —
so they need a different chunk rule (stanza or fixed-window), not just splitting. The three
long scenes can split on speech boundaries.

---

## Parked design: Ask the Scholar

A question box over the corpus. You ask "where does Shakespeare write about sleeplessness?"
or "which scenes turn on mistaken identity?" and get an answer grounded in actual scenes,
each one cited as *Work, Act N, Scene M* and linked to the reading view.

### Why not just copy `symfony/ai-demo`

The demo's blog agent is the right *shape* — a retriever exposed to an agent as the
`similarity_search` tool — and we should reuse that shape. Its weakness is upstream of
the agent, in how documents are made:

```yaml
# symfony/ai-demo config/packages/ai.yaml
Symfony\AI\Store\Document\Transformer\TextSplitTransformer: ~
```

It pulls the Symfony blog over RSS and runs `TextSplitTransformer` — fixed-size splitting
at arbitrary character offsets. A chunk can begin mid-sentence and end mid-list, and
nothing in the metadata says where you are in the document. That is the default when a
source has no structure to exploit.

We have the opposite problem, in the good sense: OSS gives us **explicit structure**
(work → act → scene → speech, with speaker attribution). `VectorService` already chunks on
scene boundaries, so every chunk is a complete dramatic unit with a real citation attached.
The demo's retrieval quality ceiling is set by its splitter; ours is set by whether a scene
is the right unit — a much better problem to have.

The second difference is citation. The demo asks the model to "provide links to the blog
posts you use as sources", which is a prompt-level request the model can quietly ignore.
We can do better, because `metadata` carries `workId`/`section`/`scene`: cite from the
retrieved documents in *our* code, not from the model's prose.

### Architecture

```
question
   ↓
agent  (ai.agent.scholar, gpt-5-mini or gpt-4.1)
   ↓  tool: similarity_search
retriever  (ai.retriever.scenes)
   ↓
store  (ai.store.postgres.scenes — pgvector, cosine, hnsw)
```

Everything below `agent` already exists. What's missing:

1. `composer require symfony/ai-agent` — brings `SimilaritySearch`.
2. A retriever and an agent in `config/packages/ai.yaml`:

   ```yaml
   ai:
       retriever:
           scenes:
               store: 'ai.store.postgres.scenes'
               vectorizer: 'ai.vectorizer.scenes'
       agent:
           scholar:
               platform: 'ai.platform.openai'
               model: 'gpt-4.1'
               prompt:
                   text: |
                       You answer questions about Shakespeare's works using only scenes
                       returned by the similarity_search tool. Never quote a line that is
                       not in the returned scenes, and never attribute a line to a speaker
                       other than the one shown. If the scenes do not answer the question,
                       say so plainly.
               tools:
                   - 'Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch'
   ```

   ```yaml
   # config/services.yaml
   Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch:
       $retriever: '@ai.retriever.scenes'
   ```

3. `ScholarController` + a Turbo-driven Twig component for the question box, rendering
   the answer alongside a citation list built from the retrieved documents' metadata.

### The parts that are actually hard

**Chunk granularity is unresolved.** Scenes are right for "which scenes turn on mistaken
identity?" and wrong for "who says 'the better part of valour'?" — the quote is one line
inside a 3,500-word chunk, and its embedding is dominated by everything around it. The
likely answer is two indexes over the same text: the existing scene store for thematic
questions, plus a speech-window store (~5 speeches, overlapping) for quotation lookup,
with the agent given both as separate tools. Do not build the second index until the first
one demonstrably fails on real questions — see Evaluation.

**Hybrid query is the cheaper half of that.** `symfony/ai-postgres-store` already supports
`HybridQuery`, combining pgvector similarity with `to_tsvector` over `metadata->>'_text'`.
Exact-phrase questions ("the better part of valour") are a full-text problem, not an
embedding problem, and we are already writing `_text` for every chunk. Try hybrid before
building a second index.

**Filtering by work or genre.** "Which comedies use twins?" should not search the tragedies.
The metadata is there (`workId`, `genre`); what needs checking is whether the store's query
path can filter on JSONB metadata, or whether we retrieve wide and filter in PHP.

**Hallucinated quotations are the failure mode that matters.** A model asked about
Shakespeare will happily produce plausible pseudo-Shakespeare. The prompt above is necessary
but not sufficient; the real guard is verifying quoted spans against the retrieved chunk
text in PHP and flagging any that don't appear verbatim. Attribution errors (right line,
wrong speaker) are the subtler version and need the same treatment.

### Evaluation

Do not ship on vibes. Build a small fixture set — 20–30 questions with known-correct
scene answers, drawn from things we can verify in the corpus:

- thematic ("scenes about sleeplessness")
- structural ("which scenes have more than 100 speeches")
- quotation ("who says 'the better part of valour'")
- negative ("does Hamlet mention America") — the correct answer is *no*, and a model that
  invents one has failed

Measure recall@5 on scene ids. That number is what tells us whether a second index or
hybrid query is worth building, and it's the only way to know whether we've actually beaten
the demo rather than just restructured it.

### Cost

Negligible and not worth optimizing. The whole corpus is 1,423,345 `cl100k_base` tokens,
about three cents to embed at `text-embedding-3-small` rates. Per-question cost is one
embedding of the question plus one chat completion over ~5 retrieved scenes.

---

## Backlog

- **Oversized chunks** — the five listed above; blocks any real `app:vectorize` run.
- **Split the index by form.** Sonnets, poems, comedies, histories, tragedies — the `genre`
  enum already carries this; the index page currently mixes all 43 works in one grid.
  Worth doing regardless of Ask the Scholar, and it gives the retriever a natural filter.
- **Local ollama embedder** — see [#1](https://github.com/survos-sites/bard/issues/1).
  Matters for iterating on chunking without a key, not for cost.
- **Paginate long plays.** Hamlet is 665KB of HTML and ~9,800 DOM nodes on one page. Fast
  today (~300ms full load), but it's the first number that degrades on a phone.
- Publish the normalized tables as a Hugging Face dataset.
- Derive stable chunks for Symfony AI vector-store tests.
