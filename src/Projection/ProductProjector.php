<?php

declare(strict_types=1);

namespace Sylius\ElasticSearchPlugin\Projection;

use ONGR\ElasticsearchBundle\Result\DocumentIterator;
use ONGR\ElasticsearchBundle\Service\Manager;
use ONGR\ElasticsearchBundle\Service\Repository;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\ElasticSearchPlugin\Document\ProductDocument;
use Sylius\ElasticSearchPlugin\Event\ProductCreated;
use Sylius\ElasticSearchPlugin\Factory\ProductDocumentFactoryInterface;

final class ProductProjector
{
    /**
     * @var Manager
     */
    private $elasticsearchManager;

    /**
     * @var Repository
     */
    private $productDocumentRepository;

    /**
     * @var ProductDocumentFactoryInterface
     */
    private $productDocumentFactory;

    /**
     * @param Manager $elasticsearchManager
     * @param ProductDocumentFactoryInterface $productDocumentFactory
     */
    public function __construct(
        Manager $elasticsearchManager,
        ProductDocumentFactoryInterface $productDocumentFactory
    ) {
        $this->elasticsearchManager = $elasticsearchManager;
        $this->productDocumentRepository = $elasticsearchManager->getRepository(ProductDocument::class);
        $this->productDocumentFactory = $productDocumentFactory;
    }

    /**
     * @param ProductCreated $event
     */
    public function handleProductCreated(ProductCreated $event): void
    {
        $this->scheduleCreatingNewProductDocuments($event->product());
        $this->scheduleRemovingOldProductDocuments($event->product());

        $this->elasticsearchManager->commit();
    }

    private function scheduleCreatingNewProductDocuments(ProductInterface $product): void
    {
        /** @var ChannelInterface[] $channels */
        $channels = $product->getChannels();
        foreach ($channels as $channel) {
            /** @var LocaleInterface[] $locales */
            $locales = $channel->getLocales();
            foreach ($locales as $locale) {
                $this->elasticsearchManager->persist($this->productDocumentFactory->createFromSyliusSimpleProductModel(
                    $product,
                    $locale,
                    $channel
                ));
            }
        }
    }

    private function scheduleRemovingOldProductDocuments(ProductInterface $product): void
    {
        /** @var DocumentIterator|ProductDocument[] $currentProductDocuments */
        $currentProductDocuments = $this->productDocumentRepository->findBy(['code' => $product->getCode()]);

        foreach ($currentProductDocuments as $sameCodeProductDocument) {
            $this->elasticsearchManager->remove($sameCodeProductDocument);
        }
    }
}
