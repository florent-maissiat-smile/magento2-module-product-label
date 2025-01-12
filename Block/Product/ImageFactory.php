<?php

declare(strict_types=1);

namespace Smile\ProductLabel\Block\Product;

use Magento\Catalog\Block\Product\Image as ImageBlock;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Catalog\Model\View\Asset\ImageFactory as AssetImageFactory;
use Magento\Catalog\Model\View\Asset\PlaceholderFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\ConfigInterface;
use Smile\ProductLabel\Block\ProductLabel\ProductLabel;

/**
 * Custom Image Factory.
 * It's a copy paste of the legacy image factory. Override is only in create() method and is highlighted.
 */
class ImageFactory extends \Magento\Catalog\Block\Product\ImageFactory
{
    private ConfigInterface $presentationConfig;

    private AssetImageFactory $viewAssetImageFactory;

    private ParamsBuilder $imageParamsBuilder;

    private ObjectManagerInterface $objectManager;

    private PlaceholderFactory $viewAssetPlaceholderFactory;

    /**
     * @param ObjectManagerInterface $objectManager               Object Manager
     * @param ConfigInterface        $presentationConfig          Presentation Config
     * @param AssetImageFactory      $viewAssetImageFactory       Images Asset Factory
     * @param PlaceholderFactory     $viewAssetPlaceholderFactory Assets Placeholder Factory
     * @param ParamsBuilder          $imageParamsBuilder          Images Param builer
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ConfigInterface $presentationConfig,
        AssetImageFactory $viewAssetImageFactory,
        PlaceholderFactory $viewAssetPlaceholderFactory,
        ParamsBuilder $imageParamsBuilder
    ) {
        $this->objectManager               = $objectManager;
        $this->presentationConfig          = $presentationConfig;
        $this->viewAssetPlaceholderFactory = $viewAssetPlaceholderFactory;
        $this->viewAssetImageFactory       = $viewAssetImageFactory;
        $this->imageParamsBuilder          = $imageParamsBuilder;
    }

    /**
     * Create image block from product
     *
     * @SuppressWarnings(PHPMD.ElseExpression) Method is inherited
     * @param Product    $product    The Product
     * @param string     $imageId    Image Id
     * @param array|null $attributes Attributes
     */
    public function create(Product $product, string $imageId, ?array $attributes = null): ImageBlock
    {
        $viewImageConfig = $this->presentationConfig->getViewConfig()->getMediaAttributes(
            'Magento_Catalog',
            ImageHelper::MEDIA_TYPE_CONFIG_NODE,
            $imageId
        );

        $imageMiscParams  = $this->imageParamsBuilder->build($viewImageConfig);
        $originalFilePath = $product->getData($imageMiscParams['image_type']);

        if ($originalFilePath === null || $originalFilePath === 'no_selection') {
            $imageAsset = $this->viewAssetPlaceholderFactory->create(
                [
                    'type' => $imageMiscParams['image_type'],
                ]
            );
        } else {
            $imageAsset = $this->viewAssetImageFactory->create(
                [
                    'miscParams' => $imageMiscParams,
                    'filePath'   => $originalFilePath,
                ]
            );
        }

        $data = [
            'data' => [
                'template'          => 'Smile_ProductLabel::product/image_with_pictos.phtml',
                'image_url'         => $imageAsset->getUrl(),
                'width'             => $imageMiscParams['image_width'],
                'height'            => $imageMiscParams['image_height'],
                'label'             => $this->getLabel($product, $imageMiscParams['image_type']),
                'ratio'             => $this->getRatio(
                    $imageMiscParams['image_width'],
                    $imageMiscParams['image_height']
                ),
                'custom_attributes' => $this->getStringCustomAttributes($attributes),
                'product_id'        => $product->getId(),
            ],
        ];

        // Override starts here.
        /** @var ProductLabel $labelsRenderer */
        $labelsRenderer = $this->objectManager->create(ProductLabel::class);
        $labelsRenderer->setProduct($product);

        $data['data']['product_labels'] = $labelsRenderer->getProductLabels();
        $data['data']['product_labels_wrapper_class'] = $labelsRenderer->getWrapperClass();

        /** @var ImageBlock $block */
        $block = $this->objectManager->create(ImageBlock::class, $data);

        return $block;
    }

    /**
     * Retrieve image custom attributes for HTML element
     *
     * @param array $attributes Attributes
     */
    private function getStringCustomAttributes(array $attributes): string
    {
        $result = [];
        foreach ($attributes as $name => $value) {
            $result[] = $name . '="' . $value . '"';
        }

        return !empty($result) ? implode(' ', $result) : '';
    }

    /**
     * Calculate image ratio
     *
     * @param int $width  Width
     * @param int $height Height
     */
    private function getRatio(int $width, int $height): float
    {
        if ($width && $height) {
            return $height / $width;
        }

        return 1.0;
    }

    /**
     * Get label
     *
     * @param Product $product   The product
     * @param string  $imageType The image type
     */
    private function getLabel(Product $product, string $imageType): string
    {
        $concat = $imageType . '_label';
        $label = $product->getData($concat);
        if (empty($label)) {
            $label = $product->getName();
        }

        return (string) $label;
    }
}
