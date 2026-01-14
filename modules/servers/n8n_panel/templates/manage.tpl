<div class="row">
    <div class="col-sm-12">

        {* =======================
           RESELLER VIEW
           ======================= *}
        {if $productType eq 'reselleraccount'}

            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-tachometer"></i> Reseller Overview
                    </h3>
                </div>

                <div class="panel-body">

                    <div class="alert alert-info text-center">
                        <strong>Reseller Administrator Access</strong>
                    </div>

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="metric-card">
                                <div class="metric-value">
                                    {$systemStats.counts.instances_total|default:0}
                                </div>
                                <div class="metric-label">Total Instances</div>
                            </div>
                        </div>

                        <div class="col-sm-4">
                            <div class="metric-card metric-success">
                                <div class="metric-value">
                                    {$systemStats.counts.instances_running|default:0}
                                </div>
                                <div class="metric-label">Running</div>
                            </div>
                        </div>

                        <div class="col-sm-4">
                            <div class="metric-card metric-danger">
                                <div class="metric-value">
                                    {$systemStats.counts.instances_stopped|default:0}
                                </div>
                                <div class="metric-label">Stopped</div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="text-center">
                        <a href="clientarea.php?action=productdetails&id={$serviceid}&dosinglesignon=1"
                           class="btn btn-primary btn-lg"
                           target="_blank">
                            <i class="fa fa-sign-in"></i> Open n8n Manager
                        </a>
                    </div>

                </div>
            </div>

        {* =======================
           INSTANCE VIEW
           ======================= *}
        {else}

            {if $instanceStats.status == 'success'}

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <i class="fa fa-server"></i> Instance Dashboard
                        </h3>
                    </div>

                    <div class="panel-body">

                        {* STATUS + DOMAIN *}
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="info-card text-center">
                                    <div class="info-title">Status</div>

                                    {if $instanceStats.instance_status == 'running'}
                                        <div class="info-value text-success">
                                            Running
                                        </div>
                                    {else}
                                        <div class="info-value text-warning">
                                            {$instanceStats.instance_status|ucfirst}
                                        </div>
                                    {/if}
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="info-card text-center">
                                    <div class="info-title">Access URL</div>
                                    <div class="info-value">
                                        <a href="http://{$instanceStats.domain}" target="_blank">
                                            {$instanceStats.domain}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {* RESOURCE USAGE *}
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="usage-card">
                                    <div class="usage-header">
                                        <i class="fa fa-microchip"></i> CPU Usage
                                    </div>

                                    <div class="usage-number">
                                        {$instanceStats.cpu_percent}%
                                    </div>

                                    <div class="progress usage-progress">
                                        <div class="progress-bar progress-bar-info"
                                             style="width: {$instanceStats.cpu_percent}%;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="usage-card">
                                    <div class="usage-header">
                                        <i class="fa fa-memory"></i> Memory Usage
                                    </div>

                                    <div class="usage-number">
                                        {$instanceStats.memory_percent}%
                                    </div>

                                    <div class="usage-subtext">
                                        {$instanceStats.memory_usage}
                                        /
                                        {$instanceStats.memory_limit}
                                    </div>

                                    <div class="progress usage-progress">
                                        <div class="progress-bar progress-bar-warning"
                                             style="width: {$instanceStats.memory_percent}%;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        {* ACTIONS *}
                        <div class="text-center">
                            <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=startInstance"
                               class="btn btn-success btn-lg {if $instanceStats.instance_status == 'running'}disabled{/if}">
                                <i class="fa fa-play"></i> Start
                            </a>

                            &nbsp;

                            <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=stopInstance"
                               class="btn btn-danger btn-lg {if $instanceStats.instance_status != 'running'}disabled{/if}">
                                <i class="fa fa-stop"></i> Stop
                            </a>
                        </div>

                    </div>
                </div>

            {else}

                <div class="alert alert-danger">
                    Unable to load instance data. Please try again later.
                </div>

            {/if}

        {/if}

    </div>
</div>

<style>
/* ===== Metrics ===== */
.metric-card {
    background: #f8f8f8;
    padding: 25px;
    border-radius: 4px;
    text-align: center;
    margin-bottom: 20px;
}

.metric-value {
    font-size: 38px;
    font-weight: 600;
}

.metric-label {
    font-size: 12px;
    text-transform: uppercase;
    color: #777;
}

.metric-success .metric-value { color: #3c763d; }
.metric-danger .metric-value { color: #a94442; }

/* ===== Info Cards ===== */
.info-card {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.info-title {
    font-size: 13px;
    color: #777;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.info-value {
    font-size: 22px;
    font-weight: 600;
}

/* ===== Usage Cards ===== */
.usage-card {
    background: #ffffff;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.usage-header {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
}

.usage-number {
    font-size: 32px;
    font-weight: 600;
    text-align: center;
    margin-bottom: 10px;
}

.usage-subtext {
    font-size: 13px;
    text-align: center;
    color: #777;
    margin-bottom: 8px;
}

.usage-progress {
    height: 10px;
    margin-bottom: 0;
}
</style>
