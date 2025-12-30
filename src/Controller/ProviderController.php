<?php

namespace App\Controller;

use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class ProviderController extends AbstractController
{
    private string $storageDir;

    public function __construct(private EntityManagerInterface $em)
    {
        $this->storageDir = $_ENV['STORAGE_DIR'] ?? dirname(__DIR__, 2) . '/storage';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0775, true);
        }

    }

    #[Route('/upload', name: 'upload_form', methods: ['GET'])]
    public function uploadForm(): Response
    {
        return $this->render('upload_form.html.twig');
    }

    #[Route('/catalog', methods: ['GET'])]
    public function catalog(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', 20)));

        $repo = $this->em->getRepository(Book::class);

        $total = (int) $this->em->createQuery(
            'SELECT COUNT(b.id) FROM App\Entity\Book b'
        )->getSingleScalarResult();

        $items = $repo->createQueryBuilder('b')
            ->orderBy('b.updatedAt', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'items' => array_map(fn(Book $b) => [
                'id' => $b->getId(),
                'title' => $b->getTitle(),
                'author' => $b->getAuthor(),
                'updatedAt' => $b->getUpdatedAt()->format(DATE_ATOM),
            ], $items),
        ]);
    }

    #[Route('/download/{id}', methods: ['GET'])]
    public function download(string $id): BinaryFileResponse|JsonResponse
    {
        $book = $this->em->getRepository(Book::class)->find($id);
        if (!$book) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $path = $this->storageDir . '/' . $book->getFileName();
        if (!is_file($path)) {
            return new JsonResponse(['error' => 'File missing'], 410);
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $book->getOriginalFileName()
        );

        return $response;
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'Missing file'], 400);
        }

        $title = $request->request->get('title')
            ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $author = $request->request->get('author') ?? 'Unknown';

        $id = $this->uuid();
        $storedName = $id . '.epub';


        $book = (new Book($id))
            ->setTitle($title)
            ->setAuthor($author)
            ->setFileName($storedName)
            ->setOriginalFileName($file->getClientOriginalName())
            ->setFileSize($file->getSize());

        $file->move($this->storageDir, $storedName);

        $this->em->persist($book);
        $this->em->flush();

        return new JsonResponse([
            'id' => $book->getId(),
            'title' => $book->getTitle(),
            'author' => $book->getAuthor(),
            'updatedAt' => $book->getUpdatedAt()->format(DATE_ATOM),
        ], 201);
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
