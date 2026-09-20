### These are the default globally available parameters that can be used as closures param. 
They are auto-injected to the closure if you pass them as param.
see vendor/filament/schemas/src/Components/Component.php*/
```php
    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByName(string $parameterName): array
    {
        return match ($parameterName) {
            'context', 'operation' => [$this->getContainer()->getOperation()],
            'get' => [$this->makeGetUtility()],
            'livewire' => [$this->getLivewire()],
            'model' => [$this->getModel()],
            'parentRepeaterItemIndex' => [$this->getParentRepeaterItemIndex()],
            'rawState' => [$this->getRawState()],
            'record' => [$this->getRecord()],
            'set' => [$this->makeSetUtility()],
            'state' => [$this->getState()],
            default => parent::resolveDefaultClosureDependencyForEvaluationByName($parameterName),
        };
    }

    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByType(string $parameterType): array
    {
        $record = is_a($parameterType, Model::class, allow_string: true) ? $this->getRecord() : null;

        if ((! $record) || is_array($record)) {
            return match ($parameterType) {
                Get::class => [$this->makeGetUtility()],
                Set::class => [$this->makeSetUtility()],
                default => parent::resolveDefaultClosureDependencyForEvaluationByType($parameterType),
            };
        }

        return match ($parameterType) {
            Model::class, $record::class => [$record],
            default => parent::resolveDefaultClosureDependencyForEvaluationByType($parameterType),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraViewData(): array
    {
        return [
            'get' => $this->makeGetUtility(),
            'operation' => $this->getContainer()->getOperation(),
            'record' => $this->getRecord(),
        ];
    }
```
