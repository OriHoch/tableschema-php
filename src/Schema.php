<?php
namespace frictionlessdata\tableschema;

/**
 *  Table Schema representation.
 *  Loads and validates a Table Schema descriptor from a descriptor / path to file / url containing the descriptor
 */
class Schema
{
    /**
     * Schema constructor.
     * @param mixed $descriptor
     * @throws Exceptions\SchemaLoadException
     * @throws Exceptions\SchemaValidationFailedException
     */
    public function __construct($descriptor)
    {
        if (Utils::isJsonString($descriptor)) {
            // it's a json encoded string
            try {
                $this->descriptor = json_decode($descriptor);
            } catch (\Exception $e) {
                throw new Exceptions\SchemaLoadException($descriptor, null, $e->getMessage());
            }
        } elseif (is_string($descriptor)) {
            // it's a url or file path
            $descriptorSource = $descriptor;
            try {
                $descriptor = file_get_contents($descriptorSource);
            } catch (\Exception $e) {
                throw new Exceptions\SchemaLoadException(null, $descriptorSource, $e->getMessage());
            }
            try {
                $this->descriptor = json_decode($descriptor);
            } catch (\Exception $e) {
                throw new Exceptions\SchemaLoadException($descriptor, $descriptorSource, $e->getMessage());
            }
        } else {
            $this->descriptor = $descriptor;
        }
        if (!is_object($this->descriptor())) {
            throw new Exceptions\SchemaLoadException($descriptor, null, "descriptor must be an object");
        }
        $validationErrors = SchemaValidator::validate($this->descriptor());
        if (count($validationErrors) > 0) {
            throw new Exceptions\SchemaValidationFailedException($validationErrors);
        };
    }

    /**
     * loads and validates the given descriptor source (php object / string / path to file / url)
     * returns an array of validation error objects
     * @param mixed $descriptor
     * @return array
     */
    public static function validate($descriptor)
    {
        try {
            new static($descriptor);
            return [];
        } catch (Exceptions\SchemaLoadException $e) {
            return [
                new SchemaValidationError(SchemaValidationError::LOAD_FAILED, $e->getMessage())
            ];
        } catch (Exceptions\SchemaValidationFailedException $e) {
            return $e->validationErrors;
        }
    }

    /**
     * @return object
     */
    public function descriptor()
    {
        return $this->descriptor;
    }

    /**
     * @return Fields\BaseField[]
     */
    public function fields()
    {
        $fields = [];
        foreach ($this->descriptor()->fields as $fieldDescriptor) {
            $field = Fields\FieldsFactory::field($fieldDescriptor);
            $fields[$field->name()] = $field;
        }
        return $fields;
    }

    public function missingValues()
    {
        return isset($this->descriptor()->missingValues) ? $this->descriptor()->missingValues : [];
    }

    /**
     * @param mixed[] $row
     * @return mixed[]
     * @throws Exceptions\FieldValidationException
     */
    public function castRow($row)
    {
        $outRow = [];
        $validationErrors = [];
        foreach ($this->fields() as $fieldName => $field) {
            $value = array_key_exists($fieldName, $row) ? $row[$fieldName] : null;
            if (in_array($value, $this->missingValues())) $value = null;
            try {
                $outRow[$fieldName] = $field->castValue($value);
            } catch (Exceptions\FieldValidationException $e) {
                $validationErrors = array_merge($validationErrors, $e->validationErrors);
            }
        }
        if (count($validationErrors) > 0) {
            throw new Exceptions\FieldValidationException($validationErrors);
        }
        return $outRow;
    }

    /**
     * @param array $row
     * @return SchemaValidationError[]
     */
    public function validateRow($row)
    {
        try {
            $this->castRow($row);
            return [];
        } catch (Exceptions\FieldValidationException $e) {
            return $e->validationErrors;
        }
    }

    protected $descriptor;
}