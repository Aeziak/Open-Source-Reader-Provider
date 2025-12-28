<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'books')]
class Book
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private string $author;

    #[ORM\Column(length: 255)]
    private string $fileName;

    #[ORM\Column(length: 255)]
    private string $originalFileName;

    #[ORM\Column(type: 'integer')]
    private int $fileSize;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getAuthor(): string { return $this->author; }
    public function getFileName(): string { return $this->fileName; }
    public function getOriginalFileName(): string { return $this->originalFileName; }
    public function getFileSize(): int { return $this->fileSize; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function setTitle(string $t): self { $this->title = $t; return $this; }
    public function setAuthor(string $a): self { $this->author = $a; return $this; }
    public function setFileName(string $n): self { $this->fileName = $n; return $this; }
    public function setOriginalFileName(string $n): self { $this->originalFileName = $n; return $this; }
    public function setFileSize(int $s): self { $this->fileSize = $s; return $this; }
}
