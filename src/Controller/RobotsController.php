<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RobotsController extends AbstractController
{
    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function index(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /uploads',
            '',
            'Sitemap: '.$this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        return new Response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
