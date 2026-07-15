<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\SeoMetadataGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fills in SEO metadata (slug, meta title, meta description) for an
 * Event whenever the client leaves them blank.
 *
 * @implements ProcessorInterface<Event, Event>
 */
final class EventWriteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private SeoMetadataGenerator $seo,
        private EventRepository $eventRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Event) {
            if (!$data->getSlug() && $data->getTitle()) {
                $data->setSlug($this->seo->uniqueSlug(
                    $data->getTitle(),
                    fn (string $candidate): bool => $this->slugTakenByAnotherEvent($candidate, $data),
                ));
            }

            if (!$data->getMetaTitle() && $data->getTitle()) {
                $data->setMetaTitle($this->seo->metaTitle($data->getTitle()));
            }

            if (!$data->getMetaDescription() && $data->getDescription()) {
                $data->setMetaDescription($this->seo->metaDescription($data->getDescription()));
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function slugTakenByAnotherEvent(string $candidate, Event $event): bool
    {
        $existing = $this->eventRepository->findOneBy(['slug' => $candidate]);

        return null !== $existing && $existing !== $event;
    }
}
