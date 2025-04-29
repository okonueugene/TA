<div>
    <div>
        <a wire:model="isActive" wire:click="switchMode({{ auth()->user()->isDark }})" checked="" class="dark-switch"
            href="#"><em class="icon ni ni-moon"></em><span>Dark Mode</span></a>
    </div>
</div>
