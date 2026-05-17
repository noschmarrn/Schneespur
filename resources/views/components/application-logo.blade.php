<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <svg class="w-10 h-10 text-blue-600" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" fill="currentColor" opacity="0.1"/>
        <path d="M12 4l1.09 3.26L16.18 8l-2.72 2.12L14 13.47 12 11.84l-2 1.63.54-3.35L7.82 8l3.09-.74L12 4z" fill="currentColor"/>
        <path d="M7 14.5c.5 0 1 .5 1.5 1s1.5 1 2.5 1 2-.5 2.5-1 1-.95 1.5-1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <path d="M5 17c.5 0 1 .5 1.5 1s1.5 1 2.5 1 2-.5 2.5-1 1-1 1.5-1 1 .5 1.5 1 1.5 1 2.5 1 2-.5 2.5-1 1-1 1.5-1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
    <span class="text-xl font-bold tracking-tight text-gray-800">{{ brand() }}</span>
</div>
