<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'attendance.require_photo' => [
                'label' => '打卡必須上傳照片',
                'type' => 'boolean',
                'default' => (bool) config('settings.attendance.require_photo', false),
                'group' => 'GPS 打卡',
                'description' => '開啟後，上工與下工打卡都必須附照片。',
            ],
            'attendance.allowed_distance_meters' => [
                'label' => '工地距離允許範圍',
                'type' => 'integer',
                'default' => (int) config('settings.attendance.allowed_distance_meters', 250),
                'group' => 'GPS 打卡',
                'description' => '打卡座標距離工程地點超過此公尺數時標記異常。',
                'min' => 0,
            ],
            'attendance.allow_manual_correction' => [
                'label' => '允許補打卡/人工修正',
                'type' => 'boolean',
                'default' => (bool) config('settings.attendance.allow_manual_correction', false),
                'group' => 'GPS 打卡',
                'description' => '保留給後續補打卡審核流程使用。',
            ],
            'company.name' => [
                'label' => '公司名稱',
                'type' => 'string',
                'default' => (string) config('settings.company.name', '工程管理系統'),
                'group' => '公司資料',
            ],
            'company.phone' => [
                'label' => '公司電話',
                'type' => 'string',
                'default' => (string) config('settings.company.phone', ''),
                'group' => '公司資料',
            ],
            'company.address' => [
                'label' => '公司地址',
                'type' => 'string',
                'default' => (string) config('settings.company.address', ''),
                'group' => '公司資料',
            ],
            'company.tax_id' => [
                'label' => '統一編號',
                'type' => 'string',
                'default' => (string) config('settings.company.tax_id', ''),
                'group' => '公司資料',
            ],
            'quotation.default_terms' => [
                'label' => '報價單預設條款',
                'type' => 'text',
                'default' => (string) config('settings.quotation.default_terms', ''),
                'group' => '報價單',
                'description' => '後續可帶入 PDF 報價單頁尾條款。',
            ],
            'payment.bank_name' => [
                'label' => '銀行名稱',
                'type' => 'string',
                'default' => (string) config('settings.payment.bank_name', ''),
                'group' => '匯款資訊',
            ],
            'payment.bank_code' => [
                'label' => '銀行代碼',
                'type' => 'string',
                'default' => (string) config('settings.payment.bank_code', ''),
                'group' => '匯款資訊',
            ],
            'payment.account_number' => [
                'label' => '匯款帳號',
                'type' => 'string',
                'default' => (string) config('settings.payment.account_number', ''),
                'group' => '匯款資訊',
            ],
            'payment.account_name' => [
                'label' => '戶名',
                'type' => 'string',
                'default' => (string) config('settings.payment.account_name', ''),
                'group' => '匯款資訊',
            ],
            'invoice.default_terms' => [
                'label' => '請款單預設條款',
                'type' => 'text',
                'default' => (string) config('settings.invoice.default_terms', ''),
                'group' => '請款單',
                'description' => '帶入請款單 PDF 頁尾條款。',
            ],
            'inventory.default_safe_stock' => [
                'label' => '材料預設安全庫存',
                'type' => 'integer',
                'default' => (int) config('settings.inventory.default_safe_stock', 0),
                'group' => '庫存',
                'min' => 0,
            ],
        ];
    }

    public function get(string $key, mixed $default = null, ?int $tenantId = null): mixed
    {
        $definitions = $this->definitions();
        $fallback = $default ?? Arr::get($definitions, "{$key}.default");
        $setting = $this->stored($tenantId)->get($key);

        if (! $setting) {
            return $fallback;
        }

        return $this->castValue($setting['value'] ?? null, $setting['type'] ?? $definitions[$key]['type'] ?? 'string', $fallback);
    }

    public function boolean(string $key, ?int $tenantId = null): bool
    {
        return (bool) $this->get($key, false, $tenantId);
    }

    public function integer(string $key, ?int $tenantId = null): int
    {
        return (int) $this->get($key, 0, $tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    public function nested(?int $tenantId = null): array
    {
        $values = [];

        foreach (array_keys($this->definitions()) as $key) {
            Arr::set($values, $key, $this->get($key, null, $tenantId));
        }

        return $values;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function grouped(?int $tenantId = null): array
    {
        $groups = [];

        foreach ($this->definitions() as $key => $definition) {
            $groups[$definition['group']][] = [
                'key' => $key,
                'label' => $definition['label'],
                'type' => $definition['type'],
                'value' => $this->get($key, null, $tenantId),
                'description' => $definition['description'] ?? null,
                'min' => $definition['min'] ?? null,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updateMany(array $values, ?int $tenantId = null, ?int $userId = null): void
    {
        foreach ($this->definitions() as $key => $definition) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            SystemSetting::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'key' => $key,
                ],
                [
                    'type' => $definition['type'],
                    'value' => ['value' => $this->normalize($values[$key], $definition['type'])],
                    'description' => $definition['description'] ?? null,
                    'updated_by' => $userId,
                ],
            );
        }

        Cache::forget($this->cacheKey($tenantId));
    }

    private function castValue(mixed $storedValue, string $type, mixed $fallback): mixed
    {
        $value = is_array($storedValue) && array_key_exists('value', $storedValue)
            ? $storedValue['value']
            : $storedValue;

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => is_numeric($value) ? (int) $value : (int) $fallback,
            default => $value === null ? $fallback : (string) $value,
        };
    }

    private function normalize(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => is_numeric($value) ? max(0, (int) $value) : 0,
            default => $value === null ? '' : (string) $value,
        };
    }

    private function stored(?int $tenantId)
    {
        return Cache::remember($this->cacheKey($tenantId), 300, fn () => SystemSetting::query()
            ->where('tenant_id', $tenantId)
            ->get(['key', 'type', 'value'])
            ->keyBy('key'));
    }

    private function cacheKey(?int $tenantId): string
    {
        return 'system_settings.'.($tenantId ?: 'global');
    }
}
