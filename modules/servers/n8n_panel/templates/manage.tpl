<div class="row">
    <div class="col-sm-12">

        {if $productType eq 'reselleraccount'}

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-tachometer"></i> Reseller Dashboard</h3>
                </div>
                <div class="panel-body">

                    <div class="alert alert-info text-center">
                        <i class="fa fa-user-secret fa-2x"></i><br>
                        Logged in as Reseller Admin
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="tile">
                                <div class="stat">{$systemStats.counts.instances_total|default:'0'}</div>
                                <div class="title">Total Instances</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="tile">
                                <div class="stat text-success">{$systemStats.counts.instances_running|default:'0'}</div>
                                <div class="title">Running</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="tile">
                                <div class="stat text-danger">{$systemStats.counts.instances_stopped|default:'0'}</div>
                                <div class="title">Stopped</div>
                            </div>
                        </div>
                    </div>

                    <br>

                    <div class="text-center">
                        <a href="clientarea.php?action=productdetails&id={$serviceid}&dosinglesignon=1" class="btn btn-primary btn-lg" target="_blank">
                            <i class="fa fa-sign-in"></i> Login to n8n Manager Panel
                        </a>
                    </div>

                </div>
            </div>

        {else}

            {if $instanceStats.status == 'success'}

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-server"></i> Instance Management</h3>
                    </div>
                    <div class="panel-body">

                        <div class="row">
                            <div class="col-sm-6">
                                <div class="well text-center">
                                    <h4>Status</h4>
                                    {if $instanceStats.instance_status == 'running'}
                                        <h2 class="text-success"><i class="fa fa-check-circle"></i> Running</h2>
                                    {else}
                                        <h2 class="text-warning"><i class="fa fa-stop-circle"></i> {$instanceStats.instance_status|ucfirst}</h2>
                                    {/if}
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="well text-center">
                                    <h4>Domain</h4>
                                    <h3><a href="http://{$instanceStats.domain}" target="_blank">{$instanceStats.domain}</a></h3>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-6">
                                <div class="panel panel-info">
                                    <div class="panel-heading">CPU Usage</div>
                                    <div class="panel-body text-center">
                                        <div class="c100 p{if $instanceStats.cpu_percent > 100}100{else}{$instanceStats.cpu_percent|string_format:"%d"}{/if} center">
                                            <span>{$instanceStats.cpu_percent}%</span>
                                            <div class="slice">
                                                <div class="bar"></div>
                                                <div class="fill"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="panel panel-info">
                                    <div class="panel-heading">Memory Usage</div>
                                    <div class="panel-body">
                                        <h3 class="text-center">{$instanceStats.memory_usage} / {$instanceStats.memory_limit}</h3>
                                        <div class="progress">
                                            <div class="progress-bar progress-bar-info" role="progressbar" aria-valuenow="{$instanceStats.memory_percent}" aria-valuemin="0" aria-valuemax="100" style="width: {$instanceStats.memory_percent}%;">
                                                {$instanceStats.memory_percent}%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-sm-12 text-center">
                                <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=startInstance" class="btn btn-success btn-lg {if $instanceStats.instance_status == 'running'}disabled{/if}">
                                    <i class="fa fa-play"></i> Start Instance
                                </a>
                                &nbsp;
                                <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=stopInstance" class="btn btn-danger btn-lg {if $instanceStats.instance_status != 'running'}disabled{/if}">
                                    <i class="fa fa-stop"></i> Stop Instance
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

            {else}
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-triangle"></i> Unable to retrieve instance stats. Please check back later.
                </div>
            {/if}

        {/if}

    </div>
</div>

<style>
.tile { background: #f5f5f5; border-radius: 4px; padding: 20px; text-align: center; margin-bottom: 20px; }
.tile .stat { font-size: 36px; font-weight: bold; line-height: 1; margin-bottom: 10px; }
.tile .title { font-size: 14px; color: #777; text-transform: uppercase; font-weight: 600; }
</style>
