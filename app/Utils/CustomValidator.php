<?php

namespace FluentEvent\App\Utils;

use FluentEvent\Framework\Validator\Validator;

class CustomValidator extends Validator
{
    // The framework (2.12.4) has no replaceRegex, so a regex rule's custom message was never used.
    // Drop this class and its binding once the framework ships one.
    protected function replaceRegex($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute . '.regex', 'regex', $originalKey);

        if (!$text) {
            return $this->generateDefaultMessage($attribute, $parameters);
        }

        return str_replace(':attribute', $attribute, $text);
    }
}
