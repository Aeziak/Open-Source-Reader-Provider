<?php

namespace App\Controller;

use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ProviderCatalogController extends AbstractController
{
    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function catalog(EntityManagerInterface $em)
    {
        $books = $em->getRepository(Book::class)->findBy([], ['updatedAt' => 'DESC']);

        return $this->render('provider/catalog.html.twig', [
            'books' => $books,
        ]);
    }

    #[Route('/books/{id}/delete', name: 'book_delete', methods: ['POST'])]
    public function delete(string $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        // CSRF via header (envoyé par fetch)
        $csrf = $request->headers->get('X-CSRF-TOKEN');
        if (!$this->isCsrfTokenValid('book_delete', $csrf)) {
            return new JsonResponse(['error' => 'CSRF invalid'], 403);
        }

        $book = $em->getRepository(Book::class)->find($id);
        if (!$book) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $em->remove($book);
        $em->flush();

        return new JsonResponse(['ok' => true, 'id' => $id]);
    }
}
