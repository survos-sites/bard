<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Survos\FieldBundle\Attribute\EntityMeta;
use Survos\FieldBundle\Attribute\Field;

#[ORM\Entity]
#[ORM\Table(name: 'bard_paragraph')]
#[ORM\Index(columns: ['work_id', 'ordinal'], name: 'paragraph_work_order')]
#[EntityMeta(icon: 'tabler:quote', group: 'Shakespeare', description: 'A speech or stage direction')]
final class Paragraph
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        public readonly int $id,
        #[ORM\ManyToOne(targetEntity: Work::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Work $work,
        #[ORM\ManyToOne(targetEntity: Chapter::class, inversedBy: 'paragraphs')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Chapter $chapter,
        #[ORM\ManyToOne(targetEntity: Character::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        public ?Character $character,
        #[ORM\Column]
        #[Field(sortable: true)]
        public int $ordinal,
        #[ORM\Column(length: 64, nullable: true)]
        #[Field(searchable: true, filterable: true)]
        public ?string $speakerCode,
        #[ORM\Column(type: 'text')]
        #[Field(searchable: true)]
        public string $text,
        #[ORM\Column(length: 1, nullable: true)]
        #[Field(filterable: true)]
        public ?string $type,
        #[ORM\Column]
        public int $characterCount,
        #[ORM\Column]
        public int $wordCount,
    ) {}
}
