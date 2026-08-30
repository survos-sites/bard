<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Survos\FieldBundle\Attribute\EntityMeta;
use Survos\FieldBundle\Attribute\Field;

#[ORM\Entity]
#[ORM\Table(name: 'bard_chapter')]
#[ORM\UniqueConstraint(name: 'chapter_location', columns: ['work_id', 'section', 'scene'])]
#[EntityMeta(icon: 'tabler:book-2', group: 'Shakespeare', description: 'An act and scene within a work')]
final class Chapter
{
    /** @var Collection<int, Paragraph> */
    #[ORM\OneToMany(targetEntity: Paragraph::class, mappedBy: 'chapter', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordinal' => 'ASC'])]
    public Collection $paragraphs;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        #[Field(sortable: true)]
        public readonly int $id,
        #[ORM\ManyToOne(targetEntity: Work::class, inversedBy: 'chapters')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Work $work,
        #[ORM\Column]
        #[Field(sortable: true, filterable: true)]
        public int $section,
        #[ORM\Column]
        #[Field(sortable: true, filterable: true)]
        public int $scene,
        #[ORM\Column(length: 255, nullable: true)]
        #[Field(searchable: true)]
        public ?string $description,
    ) {
        $this->paragraphs = new ArrayCollection();
    }
}
