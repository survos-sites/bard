<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Work;
use App\Repository\WorkRepository;
use App\Service\FountainFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WorkController extends AbstractController
{
    #[Route('/', name: 'work_index')]
    public function index(WorkRepository $works): Response
    {
        return $this->render('work/index.html.twig', ['works' => $works->findForIndex()]);
    }

    #[Route('/works/{workId}', name: 'work_show', requirements: ['workId' => '[^.]+'])]
    public function show(Work $work, WorkRepository $works): Response
    {
        $work = $works->findWithText($work->id) ?? throw $this->createNotFoundException();

        return $this->render('work/show.html.twig', ['work' => $work]);
    }

    #[Route('/works/{workId}.fountain', name: 'work_fountain')]
    public function fountain(Work $work, WorkRepository $works, FountainFormatter $formatter): Response
    {
        $work = $works->findWithText($work->id) ?? throw $this->createNotFoundException();

        return new Response($formatter->format($work), headers: [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s.fountain"', $work->id),
        ]);
    }
}
