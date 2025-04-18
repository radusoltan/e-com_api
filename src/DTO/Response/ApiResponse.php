<?php

namespace App\DTO\Response;

class ApiResponse
{
    private bool $success;
    private ?string $message = null;
    private ?string $error = null;
    private ?array $errors = null;
    private mixed $data = null;

    /**
     * Create a success response
     */
    public static function success(mixed $data = null, ?string $message = null): self
    {
        $response = new self();
        $response->success = true;
        $response->data = $data;
        $response->message = $message;

        return $response;
    }

    /**
     * Create an error response
     */
    public static function error(string $error = null, array $errors = null): self
    {
        $response = new self();
        $response->success = false;
        $response->error = $error;
        $response->errors = $errors;

        return $response;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @param string|null $message
     */
    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    /**
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @param string|null $error
     */
    public function setError(?string $error): void
    {
        $this->error = $error;
    }

    /**
     * @return array|null
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * @param array|null $errors
     */
    public function setErrors(?array $errors): void
    {
        $this->errors = $errors;
    }

    /**
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData(mixed $data): void
    {
        $this->data = $data;
    }

    /**
     * Convert response to array
     */
    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
        ];

        if ($this->message !== null) {
            $result['message'] = $this->message;
        }

        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        if ($this->errors !== null) {
            $result['errors'] = $this->errors;
        }

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        return $result;
    }
}