<?php
namespace Kcn\QtyAddtocartMessage\Model\IsProductSalableForRequestedQtyCondition;

use Magento\InventoryReservationsApi\Model\GetReservationsQuantityInterface;
use Magento\InventorySalesApi\Api\IsProductSalableForRequestedQtyInterface;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\InventorySalesApi\Api\Data\ProductSalableResultInterface;
use Magento\InventorySalesApi\Api\Data\ProductSalableResultInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\ProductSalabilityErrorInterfaceFactory;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;

class IsSalableWithReservationsCondition extends \Magento\InventorySales\Model\IsProductSalableForRequestedQtyCondition\IsSalableWithReservationsCondition
{
    /**
     * @var GetStockItemDataInterface
     */
    private $getStockItemData;

    /**
     * @var GetReservationsQuantityInterface
     */
    private $getReservationsQuantity;

    /**
     * @var GetStockItemConfigurationInterface
     */
    private $getStockItemConfiguration;

    /**
     * @var ProductSalabilityErrorInterfaceFactory
     */
    private $productSalabilityErrorFactory;

    /**
     * @var ProductSalableResultInterfaceFactory
     */
    private $productSalableResultFactory;

    private $productRepository;

    /**
     * @param GetStockItemDataInterface $getStockItemData
     * @param GetReservationsQuantityInterface $getReservationsQuantity
     * @param GetStockItemConfigurationInterface $getStockItemConfiguration
     * @param ProductSalabilityErrorInterfaceFactory $productSalabilityErrorFactory
     * @param ProductSalableResultInterfaceFactory $productSalableResultFactory
     */

    public function __construct(
        GetStockItemDataInterface $getStockItemData,
        GetReservationsQuantityInterface $getReservationsQuantity,
        GetStockItemConfigurationInterface $getStockItemConfiguration,
        ProductSalabilityErrorInterfaceFactory $productSalabilityErrorFactory,
        ProductSalableResultInterfaceFactory $productSalableResultFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
            $this->getStockItemData = $getStockItemData;
            $this->getReservationsQuantity = $getReservationsQuantity;
            $this->getStockItemConfiguration = $getStockItemConfiguration;
            $this->productSalabilityErrorFactory = $productSalabilityErrorFactory;
            $this->productSalableResultFactory = $productSalableResultFactory;
            $this->productRepository = $productRepository;
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(string $sku, int $stockId, float $requestedQty): ProductSalableResultInterface
    {
        $stockItemData = $this->getStockItemData->execute($sku, $stockId);
        if (null === $stockItemData) {
            $errors = [
                $this->productSalabilityErrorFactory->create([
                    'code' => 'is_salable_with_reservations-no_data',
                    'message' => __('The requested sku is not assigned to given stock')
                ])
            ];
            return $this->productSalableResultFactory->create(['errors' => $errors]);
        }

        $availableProductQty = floor($stockItemData['quantity']);
        $product = $this->loadMyProduct($sku);
        $productName = $product->getName();

        /** @var StockItemConfigurationInterface $stockItemConfiguration */
        $stockItemConfiguration = $this->getStockItemConfiguration->execute($sku, $stockId);

        $qtyWithReservation = $stockItemData[GetStockItemDataInterface::QUANTITY] +
            $this->getReservationsQuantity->execute($sku, $stockId);
        $qtyLeftInStock = $qtyWithReservation - $stockItemConfiguration->getMinQty() - $requestedQty;
        $isEnoughQty = (bool)$stockItemData[GetStockItemDataInterface::IS_SALABLE] && $qtyLeftInStock >= 0;
        if (!$isEnoughQty) {
            $errors = [
                $this->productSalabilityErrorFactory->create([
                    'code' => 'is_salable_with_reservations-not_enough_qty',
                    'message' => __('The requested QTY for "%1" exceeds inventory. only %2 qty available for this product.', $productName, $availableProductQty)
                ])
            ];

            return $this->productSalableResultFactory->create(['errors' => $errors]);
        }
        return $this->productSalableResultFactory->create(['errors' => []]);
    }
    public function loadMyProduct($sku)
    {
        return $this->productRepository->get($sku);
    }
}