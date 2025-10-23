<?php

namespace Icivi\RedisEventService\Dto;

use Icivi\RedisEventService\Dto\Response\SuccessResponseDto;
use Icivi\RedisEventService\Dto\Response\ErrorResponseDto;

abstract class BaseDto
{
    /**
     * Validate data for DTO
     *
     * @param BaseDto $data Data to validate
     * @return void
     */
    abstract public static function validateDtoData(BaseDto $data): void;
    /**
     * Tạo đối tượng DTO từ mảng dữ liệu
     *
     * @param array $data Mảng dữ liệu đầu vào
     * @return static
     */
    abstract public static function fromArray(array $data): static;

    /**
     * Chuyển đổi đối tượng DTO thành mảng
     *
     * @return array
     */
    abstract public function toArray(): array;


    /**
     * Tạo response thành công sử dụng SuccessResponseDto
     *
     * @param mixed $data Dữ liệu trả về
     * @param string $message Thông báo
     * @param int $statusCode Mã trạng thái HTTP
     * @return SuccessResponseDto
     */
    public static function success($data = null, string $message = 'Thao tác thành công', int $statusCode = 200): SuccessResponseDto
    {
        return new SuccessResponseDto(
            true,
            $message,
            $statusCode,
            $data
        );
    }

    /**
     * Tạo response lỗi sử dụng ErrorResponseDto
     *
     * @param string $message Thông báo lỗi
     * @param int $statusCode Mã trạng thái HTTP
     * @param mixed $errors Chi tiết lỗi
     * @return ErrorResponseDto
     */
    public static function error(string $message = 'Có lỗi xảy ra', int $statusCode = 400, $errors = null): ErrorResponseDto
    {
        return new ErrorResponseDto(
            false,
            $message,
            $statusCode,
            $errors
        );
    }

    /**
     * Trả về response JSON từ một DTO
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function toResponse()
    {
        return response()->json($this->toArray(), $this->statusCode ?? 200);
    }
}
