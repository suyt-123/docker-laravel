<?php

namespace App\Presenters\Concerns;

use Illuminate\Database\Eloquent\Model;

trait PresentsModelSummaries
{
    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>|null
     */
    protected function customerSummary(?Model $customer, array $keys = ['id', 'name']): ?array
    {
        return $this->modelOnly($customer, $keys);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>|null
     */
    protected function projectSummary(?Model $project, array $keys = ['id', 'project_no', 'name'], bool $withCustomer = false): ?array
    {
        if (! $project) {
            return null;
        }

        $summary = $this->modelOnly($project, $keys) ?? [];

        if ($withCustomer) {
            $summary['customer'] = $this->customerSummary($project->getRelationValue('customer'));
        }

        return $summary;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>|null
     */
    protected function quotationSummary(?Model $quotation, array $keys = ['id', 'quotation_no']): ?array
    {
        return $this->modelOnly($quotation, $keys);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>|null
     */
    protected function userSummary(?Model $user, array $keys = ['id', 'name']): ?array
    {
        return $this->modelOnly($user, $keys);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>|null
     */
    protected function materialSummary(?Model $material, array $keys = ['id', 'name', 'spec', 'unit', 'cost_price', 'sale_price']): ?array
    {
        return $this->modelOnly($material, $keys);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>|null
     */
    protected function modelOnly(?Model $model, array $keys): ?array
    {
        if (! $model) {
            return null;
        }

        $attributes = $model->getAttributes();
        $data = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $attributes)) {
                $data[$key] = $model->getAttribute($key);
            }
        }

        return $data;
    }
}
