<?php
declare(strict_types=1);

namespace Snowdog\BulletPoints\Model\Processor;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class BulletPoints
{
    /**
     * @var CategoryProcessor
     */
    private $categoryProcessor;

    /**
     * @var ProductProcessor
     */
    private $productProcessor;

    /**
     * @var AttributeProcessor
     */
    private $attributeProcessor;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    public function __construct(
        CategoryProcessor $categoryProcessor,
        ProductProcessor $productProcessor,
        AttributeProcessor $attributeProcessor,
        ProductRepositoryInterface $productRepository
    ) {
        $this->categoryProcessor = $categoryProcessor;
        $this->productProcessor = $productProcessor;
        $this->attributeProcessor = $attributeProcessor;
        $this->productRepository = $productRepository;
    }

    /**
     * @param array $attributeIds
     * @param array $categoryIds
     * @param array $skus
     * @return array
     */
    public function execute($attributeIds, $categoryIds = [], $skus = []): array
    {
        $categoryIds = $this->categoryProcessor->getCategories($categoryIds);
        $productIdsArray = $this->productProcessor->getProductsIds($categoryIds, $skus);
        $productIds = $productIdsArray['product_ids'];
        $attributes = $this->attributeProcessor->getAttributesData($attributeIds);

        $errors = [];
        foreach ($productIds as $productId) {
            try {
                $product = $this->getProduct($productId);
                $productAttributesData = $this->productProcessor->getProductAttributesData($product, $attributes);
                $html = $this->generateHtmlList($productAttributesData, $attributeIds);
                if (!empty($html)) {
                    $this->productProcessor->updateProductAttributeValue($product, $html);
                }
            } catch (\Exception $exception) {
                $errors[$productId] =  'Data was not updated for SKU: ' . $product->getSku()
                    . '. Error message: ' . $exception->getMessage();
            }
        }

        return [
            'errors' => $errors,
            'success' => []
        ];
    }

    /**
     * @param int $productId
     * @return ProductInterface
     * @throws NoSuchEntityException
     */
    private function getProduct($productId): ProductInterface
    {
        return $this->productRepository->getById($productId);
    }

    /**
     * @param array $data
     * @param array $attributeIds
     * @return string
     */
    private function generateHtmlList(array $data, array $attributeIds): string
    {
        if (empty($data) || empty($attributeIds)) {
            return '';
        }

        $html = '';
        foreach ($attributeIds as $attributeId) {
            if (empty($data[$attributeId]['value'])) {
                continue;
            }
            $attribute = $data[$attributeId];
            $html .= '<dt class="'
                . $attribute['code'] . '_label'
                . '">'
                . $attribute['label']
                . '</dt>'
                . '<dd class="'
                . $attribute['code'] . '_value'
                . '">'
                . $attribute['value']
                . '</dd>';
        }

        if (empty($html)) {
            return '';
        }

        return '<dl>' . $html . '</dl>';
    }
}
