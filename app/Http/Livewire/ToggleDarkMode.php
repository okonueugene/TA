<?php

namespace App\Http\Livewire;

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class ToggleDarkMode extends Component
{
    public Model $model;

    public string $field;

    public bool $isActive;

    public function mount()
    {
        $this->isActive = (bool) $this->model->getAttribute($this->field);
    }
    public function switchMode($isDark)
    {
        if ($isDark == '1') {
            auth()->user()->update([
                'isDark' => '0'
            ]);
        } else {
            auth()->user()->update([
                'isDark' => '1'
            ]);
        }
    }
    public function render()
    {
        return view('livewire.toggle-dark-mode');
    }
    public function updating($field, $value)
    {
        $this->model->setAttribute($this->field, $value)->save();
    }
}
