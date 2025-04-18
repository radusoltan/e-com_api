<?php

namespace App\Service;

use App\Exception\InvalidRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestValidatorService
{
    public function __construct(
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    /**
     * Validates that the request has JSON content type
     *
     * @throws InvalidRequestException If content type is not application/json
     */
    public function validateJsonContentType(Request $request): void
    {
        if (!str_starts_with($request->headers->get('Content-Type', ''), 'application/json')) {
            throw new InvalidRequestException('Invalid Content-Type. Expected application/json');
        }
    }

    /**
     * Parses and validates JSON payload
     *
     * @throws InvalidRequestException If JSON is invalid
     * @return array The parsed JSON data
     */
    public function parseJsonRequest(Request $request): array
    {
        $this->validateJsonContentType($request);

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new InvalidRequestException('Invalid JSON data structure');
            }
            return $data;
        } catch (\JsonException $e) {
            throw new InvalidRequestException('Invalid JSON format: ' . $e->getMessage());
        }
    }

    /**
     * Deserializes and validates a request into a DTO
     *
     * @template T
     * @param Request $request The request to deserialize
     * @param class-string<T> $dtoClass The class to deserialize into
     * @param bool $validateObject Whether to validate the object after deserialization
     * @return T The deserialized object
     * @throws InvalidRequestException If deserialization or validation fails
     */
    public function validateRequestToDto(Request $request, string $dtoClass, bool $validateObject = true)
    {
        $this->validateJsonContentType($request);

        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                $dtoClass,
                'json'
            );
        } catch (NotEncodableValueException $e) {
            throw new InvalidRequestException('Invalid JSON format: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new InvalidRequestException('Failed to process request: ' . $e->getMessage());
        }

        if ($validateObject) {
            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }
                throw new InvalidRequestException('Validation failed', $errorMessages);
            }
        }

        return $dto;
    }
}