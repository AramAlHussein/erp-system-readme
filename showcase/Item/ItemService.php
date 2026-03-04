<?php


class ItemService implements ItemServiceInterface
{
    use HandlesWarehouseBlackBox;

    /**
     * @var ItemRepositoryInterface
     */
    private ItemRepositoryInterface $repository;

    /**
     * @var UnitMeasurementService
     */
    private UnitMeasurementService $umService;


    public function __construct(ItemRepositoryInterface $repository, UnitMeasurementService $umService)
    {
        $this->repository     = $repository;
        $this->umService      = $umService;
        $this->exceptionClass = ItemException::class;
        $this->initWarehouseBlackBox('Item');
    }


    public function create(array $data): Item
    {
        try {
            $this->logTrying('create', null, null, $data);
            $item = DB::transaction(function () use ($data) {
                $item = $this->repository->create($this->prepareCreateData($data));

                $this->umService->create(
                    array_merge($data, ['item_id' => $item->id]),
                    true
                );

                return $item;
            });
            $this->logSuccess('create', $item->toArray(), $item->id);

            return $item;
        } catch (\Throwable $exMssg) {
            $this->logFailed('create', $data, null, null, [
                'code'    => $exMssg->getCode(),
                'message' => $exMssg->getMessage(),
            ]);
            $this->log('create', [
                'error_code'    => $exMssg->getCode(),
                'payload'       => $data,
                'error_message' => $exMssg->getMessage(),
            ]);

            throw $exMssg;
        }
    }
}
