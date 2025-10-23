<?php

namespace Icivi\RedisEventService\Dtos\Response;

use Icivi\RedisEventService\Dtos\BaseDto;
use Illuminate\Support\Facades\Validator;

class SuccessResponseDto extends BaseDto
{
    /**
     * @param bool $success Trạng thái thành công
     * @param string $message Thông báo
     * @param int $statusCode Mã trạng thái HTTP
     * @param mixed $data Dữ liệu trả về
     */
    public function __construct(
        public readonly bool $success = true,
        public readonly string $message = 'Thao tác thành công',
        public readonly int $statusCode = 200,
        public readonly mixed $data = null,
    ) {}

    /**
     * Validate data for DTO
     *
     * @param BaseDto $data Data to validate
     * @return void
     */
    public static function validateDtoData(BaseDto $data): void
    {
        $validator = Validator::make($data->toArray(), [
            'success' => 'required|boolean',
            'message' => 'required|string',
            'status_code' => 'required|integer',
            'data' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            throw new \Exception('Dữ liệu không hợp lệ');
        }
    }
    /**
     * Tạo đối tượng DTO từ mảng dữ liệu
     *
     * @param array $data Mảng dữ liệu đầu vào
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            $data['success'] ?? true,
            $data['message'] ?? 'Thao tác thành công',
            $data['status_code'] ?? 200,
            $data['data'] ?? null,
        );
    }

    /**
     * Chuyển đổi đối tượng DTO thành mảng
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'status_code' => $this->statusCode,
            'data' => $this->data,
        ];
    }
}
