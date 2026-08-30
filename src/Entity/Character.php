<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Survos\FieldBundle\Attribute\EntityMeta;
use Survos\FieldBundle\Attribute\Field;
use Survos\FieldBundle\Attribute\RouteIdentity;
use Survos\FieldBundle\Entity\RouteIdentityTrait;
use Survos\FieldBundle\Entity\RouteParametersInterface;

#[ORM\Entity]
#[ORM\Table(name: 'bard_character')]
#[EntityMeta(icon: 'tabler:masks-theater', group: 'Shakespeare', description: 'A named speaker in one or more works')]
#[RouteIdentity(field: 'id')]
final class Character implements RouteParametersInterface
{
    use RouteIdentityTrait;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 64)]
        #[Field(searchable: true, sortable: true)]
        public readonly string $id,
        #[ORM\Column(length: 128)]
        #[Field(searchable: true, sortable: true)]
        public string $name,
        #[ORM\Column(length: 64, nullable: true)]
        #[Field(searchable: true)]
        public ?string $abbreviation,
        #[ORM\Column(length: 255, nullable: true)]
        #[Field(searchable: true)]
        public ?string $description,
        #[ORM\Column]
        #[Field(sortable: true)]
        public int $speechCount,
    ) {}
}
