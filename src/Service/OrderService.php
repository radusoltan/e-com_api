<?php

namespace App\Service;

use App\DTO\Response\ApiResponse;
use App\DTO\Response\ResponsePaginator;
use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepository,
        private readonly CacheService $cacheService
    ) {}

    public function list(int $page = 1, int $limit = 20): array
    {
        $cacheKey = "orders:page:$page:limit:$limit";

        return $this->cacheService->remember($cacheKey, 300, function () use ($page, $limit) {
            $orders = $this->orderRepository->findPaginated($page, $limit);
            $total = $this->orderRepository->count([]);
            return ResponsePaginator::paginated($orders, $page, $total, $limit);
        });
    }

    public function get(int $id): array
    {
        $cacheKey = "order:$id";

        return $this->cacheService->remember($cacheKey, 300, function () use ($id) {
            $order = $this->orderRepository->find($id);

            if (!$order) {
                return ApiResponse::error("Order #$id not found")->toArray();
            }

            return ApiResponse::success($order)->toArray();
        });
    }

    public function create(array $data): array
    {
        try {
            $order = new Order();
            $order->setOrderNumber($data['orderNumber'] ?? uniqid('ORD'));
            $order->setStatus($data['status']); // trebuie să fie un obiect OrderStatus
            $order->setTotalAmount($data['totalAmount']);

            $this->em->persist($order);
            $this->em->flush();

            $this->cacheService->invalidateTags(['orders']);

            return ApiResponse::success($order, "Order created successfully")->toArray();
        } catch (\Throwable $e) {
            return ApiResponse::error("Failed to create order", ['exception' => $e->getMessage()])->toArray();
        }
    }

    public function update(int $id, array $data): array
    {
        $order = $this->orderRepository->find($id);

        if (!$order) {
            return ApiResponse::error("Order #$id not found")->toArray();
        }

        try {
            if (isset($data['status'])) {
                $order->setStatus($data['status']);
            }

            if (isset($data['totalAmount'])) {
                $order->setTotalAmount($data['totalAmount']);
            }

            $this->em->flush();

            $this->cacheService->invalidateTags(['orders', "order:$id"]);

            return ApiResponse::success($order, "Order updated successfully")->toArray();
        } catch (\Throwable $e) {
            return ApiResponse::error("Failed to update order", ['exception' => $e->getMessage()])->toArray();
        }
    }

    public function delete(int $id): array
    {
        $order = $this->orderRepository->find($id);

        if (!$order) {
            return ApiResponse::error("Order #$id not found")->toArray();
        }

        try {
            $this->em->remove($order);
            $this->em->flush();

            $this->cacheService->invalidateTags(['orders', "order:$id"]);

            return ApiResponse::success(null, "Order deleted successfully")->toArray();
        } catch (\Throwable $e) {
            return ApiResponse::error("Failed to delete order", ['exception' => $e->getMessage()])->toArray();
        }
    }
}