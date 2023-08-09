<?php

namespace PowerComponents\LivewirePowerGrid\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Arr, Js};
use Illuminate\View\ComponentAttributeBag;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Components\Actions\ActionsController;
use PowerComponents\LivewirePowerGrid\Components\Rules\{RulesController};
use PowerComponents\LivewirePowerGrid\Themes\ThemeBase;

class Actions
{
    protected ComponentAttributeBag $componentBag;

    public array $parameters;

    protected Helpers $helperClass;

    public array $ruleRedirect = [];

    public bool $ruleDisabled;

    public bool $ruleHide;

    public array $ruleAttributes;

    public array $ruleDispatch;

    public array $ruleDispatchTo;

    public string $ruleCaption;

    public array $ruleBladeComponent;

    public bool $isButton = false;

    public bool $isRedirectable = false;

    public bool $isLinkeable = false;

    public ?string $bladeComponent = null;

    public ?string $customRender = null;

    public ComponentAttributeBag $bladeComponentParams;

    protected array $attributes = [];

    public function __construct(
        public array|Button $action,
        public Model|\stdClass $row,
        public string|int $primaryKey,
        public ThemeBase $theme,
    ) {
        $this->componentBag = new ComponentAttributeBag();
        $this->helperClass  = new Helpers();

        $this->initializeParameters();

        $this->actionRules();
        $this->dispatch();
        //        $this->dispatchTo();
        $this->title();
       // $this->bladeComponent();
        $this->disabled();
        $this->redirect();
        // $this->toggleDetail();
        $this->attributes();
        $this->route();
        $this->id();
        $this->customRender();

        if ($this->hasAttributesInComponentBag('wire:click')
            || data_get($this->action, 'caption')
            && blank(data_get($this->action, 'route'))
        ) {
            $this->isButton = true;
        }

        if (filled($this->ruleRedirect)) {
            $this->isLinkeable = true;
        }
    }

    private function initializeParameters(): void
    {
        $customParams = resolve(ActionsController::class)->recoverFromButton($this->action, $this->row);

        if (filled($customParams) && is_array($customParams['custom-action'])) {
            $this->parameters = $customParams['custom-action'];

            return;
        }

        // $this->parameters = $this->helperClass->makeActionParameters((array)data_get($this->action, 'params'), $this->row);
    }

    private function actionRules(): void
    {
        $rules = resolve(RulesController::class)->recoverFromButton($this->action, $this->row);

        $this->ruleRedirect       = (array) data_get($rules, 'redirect', []);
        $this->ruleDisabled       = boolval(data_get($rules, 'disable', false));
        $this->ruleHide           = boolval(data_get($rules, 'hide', false));
        $this->ruleAttributes     = (array) (data_get($rules, 'setAttribute', []));
        $this->ruleBladeComponent = (array) (data_get($rules, 'bladeComponent', []));
        $this->ruleEmit           = (array) data_get($rules, 'emit', []);
        $this->ruleEmitTo         = (array) data_get($rules, 'emitTo', []);
        $this->ruleCaption        = strval(data_get($rules, 'caption'));
    }

    private function attributes(): void
    {
        $class = filled(data_get($this->action, 'class')) ? data_get($this->action, 'class') : $this->theme->actions->headerBtnClass;

        $this->resolveManyAttributes();

        $this->componentBag = $this->componentBag->class($class);
    }

    private function resolveManyAttributes(): void
    {
        if (filled($this->ruleAttributes)) {
            $value = null;

            foreach ($this->ruleAttributes as $attribute) {
                if (is_string($attribute['value'])) {
                    $value = $attribute['value'];
                }

                if (is_array($attribute['value'])) {
                    if (is_array($attribute['value'][1])) {
                        $value = $attribute['value'][0] . '(' . json_encode($this->helperClass->makeActionParameters($attribute['value'][1], $this->row)) . ')';
                    } else {
                        $value = $attribute['value'][0] . '(' . $attribute['value'][1] . ')';
                    }
                }

                $this->componentBag = $this->componentBag->merge([$attribute['attribute'] => $value]);
            }
        }
    }

    private function hasAttributesInComponentBag(string $attribute): bool
    {
        $ruleExist = Arr::where(
            $this->ruleAttributes ?? [],
            function ($attributes) use ($attribute) {
                return $attributes['attribute'] === $attribute;
            }
        );

        return count($ruleExist) > 0 || $this->componentBag->has($attribute);
    }

    private function redirect(): void
    {
        if (blank($this->ruleRedirect)) {
            return;
        }

        $this->componentBag = $this->componentBag->merge([
            'href'   => $this->ruleRedirect['url'],
            'target' => $this->ruleRedirect['target'],
        ]);
    }

    private function emit(): void
    {
        if ((
            $this->hasAttributesInComponentBag('wire:click')
            || blank(data_get($this->action, 'event'))
            || filled(data_get($this->action, 'to'))
        ) && blank($this->ruleEmit)) {
            return;
        }

        $event = $this->ruleEmit['event'] ?? data_get($this->action, 'event');

        $parameters = $this->parameters;

        if (isset($this->ruleEmit['params'])) {
            $parameters = $this->helperClass->makeActionParameters($this->ruleEmit['params'], $this->row);
        }

        $this->componentBag = $this->componentBag->merge([
            'wire:click' => '$emit("' . $event . '", ' . json_encode($parameters) . ')',
        ]);
    }

    private function emitTo(): void
    {
        if (($this->hasAttributesInComponentBag('wire:click')
            || blank(data_get($this->action, 'to'))) && blank($this->ruleEmitTo)) {
            return;
        }

        $to = $this->ruleEmitTo['to'] ?? data_get($this->action, 'to');

        $event = $this->ruleEmitTo['event'] ?? data_get($this->action, 'event');

        $parameters = $this->parameters;

        if (isset($this->ruleEmitTo['params'])) {
            $parameters = $this->helperClass->makeActionParameters($this->ruleEmitTo['params'], $this->row);
        }

        $this->componentBag = $this->componentBag->merge([
            'wire:click' => '$emitTo("' . $to . '", "' . $event . '", ' . json_encode($parameters) . ')',
        ]);
    }

    private function dispatch(): void
    {
        if ($this->hasAttributesInComponentBag('wire:click')) {
            return;
        }

        $event = $this->ruleDispatch['dispatchEvent'] ?? data_get($this->action, 'dispatchEvent');

        $parameters = $this->parameters;

        if (isset($this->ruleEmit['params'])) {
            $parameters = $this->helperClass->makeActionParameters($this->ruleEmit['params'], $this->row);
        }

        $this->componentBag = $this->componentBag->merge([
            'wire:click' => '$dispatch("' . $event . '", ' . Js::encode($parameters) . ' )',
        ]);
    }

    private function toggleDetail(): void
    {
        if (!data_get($this->action, 'toggleDetail')) {
            return;
        }

        $this->componentBag = $this->componentBag->merge([
            'wire:click.prevent' => 'toggleDetail("' . $this->row->{$this->primaryKey} . '")',
        ]);
    }

    private function disabled(): void
    {
        if ($this->hasAttributesInComponentBag('disabled')) {
            return;
        }

        if (!$this->ruleDisabled) {
            return;
        }

        $this->componentBag = $this->componentBag->merge([
            'disabled' => 'disabled',
        ]);
    }

    private function title(): void
    {
        if ($this->hasAttributesInComponentBag('title')) {
            return;
        }

        $this->componentBag = $this->componentBag->merge([
            'title' => strval(data_get($this->action, 'tooltip')),
        ]);
    }

    public function caption(): ?string
    {
        return $this->ruleCaption ?: strval(data_get($this->action, 'caption'));
    }

    private function bladeComponent(): void
    {
        $component = strval(data_get($this->action, 'bladeComponent'));

        if (filled($this->ruleBladeComponent)) {
            $component  = $this->ruleBladeComponent['component'];
            $parameters = $this->helperClass->makeActionParameters((array) data_get($this->ruleBladeComponent, 'params', []), $this->row);
        }

        $customParams = resolve(ActionsController::class)->recoverFromButton($this->action, $this->row);

        if (filled($customParams) && is_array($customParams['custom-action'])) {
            $parameters = $customParams['custom-action'];
        } else {
            $parameters = $parameters ?? $this->parameters;
        }

        $this->bladeComponentParams = new ComponentAttributeBag($parameters);

        $this->bladeComponent = $component;
    }

    private function route(): void
    {
        if (!data_get($this->action, 'route')) {
            return;
        }

        $this->isRedirectable = true;
    }

    public function getAttributes(): ComponentAttributeBag
    {
        return $this->componentBag;
    }

    private function id(): void
    {
        if (filled(data_get($this->action, 'id'))) {
            $this->componentBag = $this->componentBag->merge([
                'id' => data_get($this->action, 'id') . '-' . $this->row->{$this->primaryKey},
            ]);
        }
    }

    private function customRender(): void
    {
        $customParams = resolve(ActionsController::class)->recoverFromButton($this->action, $this->row);

        if (filled($customParams) && is_string($customParams['custom-action'])) {
            $this->customRender = $customParams['custom-action'];
        }
    }

    public function getDynamicProperty(string $key): mixed
    {
        return data_get(data_get($this->action, 'dynamicProperties'), $key);
    }
}
