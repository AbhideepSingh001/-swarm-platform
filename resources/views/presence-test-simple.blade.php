<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reverb Test</title>
    @vite(['resources/js/app.js'])
</head>
<body>
    <h1>Reverb Connection Test</h1>
    <div id="status">Connecting...</div>
    <div id="events"></div>

    <script type="module">
        document.getElementById('status').textContent = 'Echo loaded: ' + (window.Echo ? 'YES' : 'NO');

        if (window.Echo) {
            const channel = window.Echo.channel('swarm.presence');

            channel.subscribed(() => {
                document.getElementById('status').textContent = 'Connected to swarm.presence';
            });

            channel.listen('.agent.online', (e) => {
                const div = document.createElement('div');
                div.textContent = `Agent ${e.agent_id} (${e.name}) came ONLINE`;
                document.getElementById('events').appendChild(div);
            });

            channel.listen('.agent.offline', (e) => {
                const div = document.createElement('div');
                div.textContent = `Agent ${e.agent_id} (${e.name}) went OFFLINE`;
                document.getElementById('events').appendChild(div);
            });

            channel.listen('.agent.presence.updated', (e) => {
                const div = document.createElement('div');
                div.textContent = `Presence updated: ${JSON.stringify(e)}`;
                document.getElementById('events').appendChild(div);
            });
        }
    </script>
</body>
</html>