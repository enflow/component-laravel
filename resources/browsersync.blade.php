@if (app()->environment() == 'local')
    <script async src="{{ config('app.url') }}:3000/browser-sync/browser-sync-client.js"></script>
@endif
