<?php

declare(strict_types=1);

namespace NFSeNacional\Validator;

use DOMDocument;
use NFSeNacional\Exception\ValidationException;

class SchemaValidator
{
    public function __construct(
        private readonly string $schemaDirectory
    ) {
    }

    public function validate(string $xml, SchemaType $type): void
    {
        $schemaPath = $this->schemaDirectory . DIRECTORY_SEPARATOR . $type->value . '.xsd';
        if (!is_file($schemaPath)) {
            throw new ValidationException("Schema {$type->value} não encontrado ({$schemaPath})");
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        libxml_use_internal_errors(true);
        if (!$dom->schemaValidate($schemaPath)) {
            $errors = array_map(
                static fn(\LibXMLError $error): string => trim($error->message),
                libxml_get_errors()
            );
            libxml_clear_errors();

            throw new ValidationException('XML inválido segundo o schema: ' . implode('; ', $errors));
        }
    }
}

