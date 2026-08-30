<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Genre;
use App\Repository\WorkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Survos\FieldBundle\Attribute\EntityMeta;
use Survos\FieldBundle\Attribute\Field;
use Survos\FieldBundle\Attribute\RouteIdentity;
use Survos\FieldBundle\Entity\RouteIdentityTrait;
use Survos\FieldBundle\Entity\RouteParametersInterface;

#[ORM\Entity(repositoryClass: WorkRepository::class)]
#[ORM\Table(name: 'bard_work')]
#[EntityMeta(icon: 'tabler:script', group: 'Shakespeare', description: 'A play, poem, or sonnet collection')]
#[RouteIdentity(field: 'id')]
final class Work implements RouteParametersInterface
{
    use RouteIdentityTrait;

    /** @var Collection<int, Chapter> */
    #[ORM\OneToMany(targetEntity: Chapter::class, mappedBy: 'work', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['section' => 'ASC', 'scene' => 'ASC'])]
    public Collection $chapters;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 32)]
        #[Field(searchable: true, sortable: true)]
        public readonly string $id,
        #[ORM\Column(length: 128)]
        #[Field(searchable: true, sortable: true)]
        public string $title,
        #[ORM\Column(length: 255)]
        #[Field(searchable: true)]
        public string $longTitle,
        #[ORM\Column]
        #[Field(sortable: true, filterable: true)]
        public int $year,
        #[ORM\Column(enumType: Genre::class)]
        #[Field(filterable: true, facet: true)]
        public Genre $genre,
        #[ORM\Column(length: 32, nullable: true)]
        #[Field(filterable: true, facet: true)]
        public ?string $source,
        #[ORM\Column]
        #[Field(sortable: true)]
        public int $totalWords,
        #[ORM\Column]
        #[Field(sortable: true)]
        public int $totalParagraphs,
    ) {
        $this->chapters = new ArrayCollection();
    }
}
