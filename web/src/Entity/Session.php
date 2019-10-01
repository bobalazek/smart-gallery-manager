<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\SessionRepository")
 * @ORM\Table(name="sessions")
 */
class Session
{
    /**
     * @ORM\Id()
     * @ORM\Column(name="sess_id", type="string", length=128)
     */
    private $id;

    /**
     * @ORM\Column(name="sess_data", nullable=false, type="blob")
     */
    private $data;

    /**
     * @ORM\Column(name="sess_time", type="integer", nullable=false, options={"unsigned": true})
     */
    private $time;

    /**
     * @ORM\Column(name="sess_lifetime", nullable=false, type="integer")
     */
    private $lifetime;
}
