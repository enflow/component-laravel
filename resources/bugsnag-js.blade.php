@if (in_array(app()->environment(), config('bugsnag.notify_release_stages', [])))
    <script src="https://d2wy8f7a9ursnm.cloudfront.net/v4/bugsnag.min.js"></script>
    <script>window.bugsnagClient = bugsnag('{{ config('services.bugsnag.api_key') }}')</script>
@else
    <!-- skipped bugsnag JS due to env. not in notify release stages -->
@endif
