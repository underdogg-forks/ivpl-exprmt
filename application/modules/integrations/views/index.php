<div class="headerbar">
    <h1><?php _trans('integrations'); ?></h1>
</div>

<div class="content">
    <form method="post" class="form-horizontal">
        <?php _csrf_field(); ?>
        <?php _csrf_field(); ?>
        <div class="panel panel-default">
            <div class="panel-heading"><?php _trans('letspeppol'); ?></div>
            <div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="base_url"><?php _trans('letspeppol_base_url'); ?></label>
                    <div class="col-sm-6">
                        <input type="text" class="form-control" name="base_url" id="base_url" value="<?php echo htmlsc($settings['base_url'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="client_id"><?php _trans('letspeppol_client_id'); ?></label>
                    <div class="col-sm-6">
                        <input type="text" class="form-control" name="client_id" id="client_id" value="<?php echo htmlsc($settings['client_id'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="client_secret"><?php _trans('letspeppol_client_secret'); ?></label>
                    <div class="col-sm-6">
                        <input type="password" class="form-control" name="client_secret" id="client_secret" value="">
                    </div>
                </div>
            </div>
            <div class="panel-footer">
                <button type="submit" name="btn_submit" value="1" class="btn btn-success">
                    <i class="fa fa-save"></i> <?php _trans('save'); ?>
                </button>
            </div>
        </div>
    </form>
</div>
