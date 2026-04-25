<?php

class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = trim((string) ($data[$field] ?? ''));
            $ruleList = explode('|', $ruleString);

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && $value === '') {
                    $errors[$field][] = 'هذا الحقل مطلوب.';
                }

                if ($rule === 'numeric' && $value !== '' && !is_numeric($value)) {
                    $errors[$field][] = 'يجب أن تكون القيمة رقمية.';
                }

                if ($rule === 'date' && $value !== '' && strtotime($value) === false) {
                    $errors[$field][] = 'صيغة التاريخ غير صحيحة.';
                }
            }
        }

        return $errors;
    }
}
