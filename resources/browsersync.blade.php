@if (app()->environment() == 'local')
    <script type='text/javascript' id="__bs_script__">//<![CDATA[
    document.write("<script async src='//HOST:3000/browser-sync/browser-sync-client.js'><\/script>".replace("HOST", location.hostname));
    //]]></script>
@endif
