<div class="row">
    <div class="col-sm-12">
        <h3>Instance Status</h3>

        {if $instanceStats.status == 'success'}
            <div class="alert {if $instanceStats.instance_status == 'running'}alert-success{else}alert-warning{/if}">
                Status: <strong>{$instanceStats.instance_status|ucfirst}</strong>
            </div>

            <div class="row">
                <div class="col-sm-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">CPU Usage</h3>
                        </div>
                        <div class="panel-body text-center">
                            <h2>{$instanceStats.cpu_percent}%</h2>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Memory Usage</h3>
                        </div>
                        <div class="panel-body text-center">
                            <h2>{$instanceStats.memory_usage} / {$instanceStats.memory_limit}</h2>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" aria-valuenow="{$instanceStats.memory_percent}" aria-valuemin="0" aria-valuemax="100" style="width: {$instanceStats.memory_percent}%;">
                                    {$instanceStats.memory_percent}%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p><strong>Domain:</strong> <a href="http://{$instanceStats.domain}" target="_blank">{$instanceStats.domain}</a></p>

            <hr>

            <div class="text-center">
                <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=startInstance" class="btn btn-success">Start Instance</a>
                <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=stopInstance" class="btn btn-danger">Stop Instance</a>
            </div>

        {else}
            <div class="alert alert-danger">
                Unable to retrieve instance stats.
            </div>
        {/if}
    </div>
</div>
