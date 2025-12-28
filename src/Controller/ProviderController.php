<?php

namespace App\Controller;

use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class ProviderController extends AbstractController
{
    private string $storageDir;

    public function __construct(private EntityManagerInterface $em)
    {
        $this->storageDir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0775, true);
        }
    }

    #[Route('/ui/upload', name: 'ui_upload_form', methods: ['GET'])]
    public function uploadForm(): Response
    {
        $html = <<<HTML
            <!doctype html>
            <html lang="fr">
            <head>
            <meta charset="utf-8" />
            <meta name="viewport" content="width=device-width,initial-scale=1" />
            <title>Provider — Upload EPUB</title>
            <style>
                body { font-family: sans-serif; margin: 20px; max-width: 720px; }
                .box { border: 1px solid #000; padding: 14px; }
                label { display:block; margin-top: 10px; }
                input { width: 100%; padding: 8px; margin-top: 6px; box-sizing: border-box; }
                button { margin-top: 14px; padding: 10px 14px; }
                .small { font-size: 12px; opacity: .8; margin-top: 8px; }
                pre { white-space: pre-wrap; background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
            </style>
            </head>
            <body>
            <h1>Upload EPUB</h1>

            <div class="box">
                <form id="upload-form" action="/upload" method="post" enctype="multipart/form-data">
                <label>Fichier (.epub)
                    <input type="file" name="file" accept=".epub,application/epub+zip" required />
                </label>

                <label>Titre
                    <input type="text" name="title" placeholder="Mon livre" />
                </label>

                <label>Auteur
                    <input type="text" name="author" placeholder="Auteur" />
                </label>

                <button type="submit">Uploader</button>
                <div class="small">Envoie sur <code>POST /upload</code>. Ensuite tu peux vérifier <code>/catalog</code>.</div>
                </form>
            </div>

            <h2>Résultat</h2>
            <pre id="result">—</pre>

            <script>
                const form = document.getElementById('upload-form');
                const result = document.getElementById('result');

                form.addEventListener('submit', async (e) => {
                e.preventDefault();
                result.textContent = "Upload...";
                try {
                    const fd = new FormData(form);
                    const r = await fetch(form.action, { method: "POST", body: fd });
                    const text = await r.text();
                    if (!r.ok) throw new Error(text);
                    result.textContent = text;

                    // petit lien utile
                    try {
                    const j = JSON.parse(text);
                    if (j.id) {
                        result.textContent += "\\n\\nTests:\\n/catalog?page=1&pageSize=20\\n/download/" + j.id + "\\n";
                    }
                    } catch {}
                } catch (err) {
                    result.textContent = "Erreur:\\n" + (err?.message || String(err));
                }
                });
            </script>
            </body>
            </html>
        HTML;

        return new Response($html);
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

    #[Route('/upload', methods: ['POST'])]
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

        $file->move($this->storageDir, $storedName);

        $book = (new Book($id))
            ->setTitle($title)
            ->setAuthor($author)
            ->setFileName($storedName)
            ->setOriginalFileName($file->getClientOriginalName())
            ->setFileSize($file->getSize());

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
