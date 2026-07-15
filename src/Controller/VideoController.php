<?php

namespace App\Controller;

use App\Entity\Enum\MediaType;
use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VideoController extends AbstractController
{
    public function __construct(
        private MediaRepository $mediaRepository,
    ) {
    }

    #[Route('/videos', name: 'app_videos', methods: ['GET'])]
    public function index(): Response
    {
        $videos = $this->mediaRepository->findLatestPublishedByType(MediaType::Video, 60);

        return $this->render('video/index.html.twig', [
            'videos' => $videos,
        ]);
    }
}
