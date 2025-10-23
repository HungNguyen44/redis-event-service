<?php

namespace Icivi\RedisEventService\Dtos\Response;

use Icivi\RedisEventService\Dtos\BaseDto;
use Illuminate\Support\Facades\Validator;


class ErrorResponseDto extends BaseDto
{
    /**
     * @param bool $success Trạng thái thành công
     * @param string $message Thông báo lỗi
     * @param int $statusCode Mã trạng thái HTTP
     * @param mixed $errors Chi tiết lỗi
     */
    public function __construct(
        public readonly bool $success = false,
        public readonly string $message = 'Có lỗi xảy ra',
        public readonly int $statusCode = 400,
        public readonly mixed $errors = null,
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
            'errors' => 'nullable|array',
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
            $data['success'] ?? false,
            $data['message'] ?? 'Có lỗi xảy ra',
            $data['status_code'] ?? 400,
            $data['errors'] ?? null,
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
            'errors' => $this->errors,
        ];
    }
}
