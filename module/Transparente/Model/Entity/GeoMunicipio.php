<?php
namespace Transparente\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use \Transparente\Model\Entity\AbstractDoctrineEntity;

/**
 * @ORM\Entity
 * @ORM\Table(name="geo_municipios")
 */
class GeoMunicipio extends AbstractDoctrineEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $nombre;

    /**
     * @ORM\Column(type="string")
     */
    protected $nombre_guatecompras;

    /**
     * @ORM\ManyToOne(targetEntity="GeoDepartamento", inversedBy="municipios")
     * @ORM\JoinColumn(name="id_geo_departamento", referencedColumnName="id")
     */
    protected $departamento;

    public function getId()
    {
        return $this->id;
    }

    public function getNombre ()
    {
        return $this->nombre;
    }

    public function getDepartamento ()
    {
        return $this->departamento;
    }


}