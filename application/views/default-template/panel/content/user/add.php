<?php
/**
 * Created by PhpStorm.
 * User: Jair
 * Date: 12/4/2018
 * Time: 22:33
 */
?>
<div class="container-fluid box-shadow-2">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Agregar usuario</h1>
        </div>
        <div class="col-md-12">
            <?php
            $this->load->view("default-template/flash-data-basic-messages");
            ?>
        </div>
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    Agregue la informacion del usuario
                </div>

                <div class="panel-body">
                    <div class="row">
                        <form role="form" method="post" name="user-add-form" data-parsley-validate>
                            <div class="col-lg-6">
                                    <input type="hidden" name="user-id" value="">
                                    <div class="form-group">
                                        <label>Nombre</label>
                                        <input class="form-control" required name="first-name" value="<?=set_value("first-name")?>" placeholder="Enter first name">
                                    </div>
                            </div>
                            <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Apellido</label>
                                        <input class="form-control" required name="last-name" value="<?=set_value("last-name")?>" placeholder="Enter last name">
                                    </div>
                            </div>
                            <div class="col-lg-4">
                                    <div class="form-group">
                                        <label>Correo</label>
                                        <input type="email" class="form-control" required name="email" value="<?=set_value("email")?>" placeholder="Enter email">
                                    </div>
                            </div>
                            <div class="col-lg-4">
                                    <div class="form-group">
                                        <label>Contraseña</label>
                                        <input class="form-control" required name="password" placeholder="Enter password" value="" id="user-password">
                                    </div>
                            </div>
                            <div class="col-lg-4">
                                    <div class="form-group">
                                        <label>Confirm password</label>
                                        <input class="form-control" required name="confirm-password" placeholder="Confirm password" value="" data-parsley-equalto="#user-password" data-parsley-equalto-message="Password and confirm password are different">
                                    </div>
                            </div>

                            <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Roles</label>
                                        <?php
                                        $html = "";
                                        $i = 0;
                                        foreach ($roleList as $role)
                                        {
                                            $role = (array)$role;
                                            $parsleyValidation = $i == 0?' required data-parsley-errors-container="#role-error-container" data-parsley-error-message="Choose at least one role" ':'';
                                            $html .= '
                                            <div class="checkbox">
                                                <label>
                                                    <input type="checkbox" name="roles[]" value="'.$role["id_rol"].'" '.$parsleyValidation.'>'.$role["rolename_rol"].'
                                                </label>
                                            </div>
                                            ';
                                        }
                                        echo $html;
                                        ?>
                                        <div id="role-error-container"></div>
                                    </div>

                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Responsable en los siguiente estados</label>
                                    <?php
                                    $html = "<br>";
                                    foreach ($responsibleGroupList as $group => $statuses)
                                    {
                                        $html .="
                                        <div class='checkbox'>
                                            <label style='font-weight: bold;'>
                                                <input type='checkbox' name='responsible-group[]' value='".$group."'>".$group.'
                                            </label>
                                        </div>
                                        <ol>
                                        ';
                                        foreach ($statuses as $status)
                                        {
                                            $html .= '
                                            <li>'.$status.'</li>
                                            ';
                                        }
                                        $html .="</ol>";
                                    }
                                    echo $html;
                                    ?>
                                </div>
                            </div>
                            <!-- <div class="col-md-12">
                                <div class="form-group">
                                    <label>Responsable en</label>
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="responsible-group" value="building_process">Proceso de construccion
                                        </label>
                                    </div>
                                </div>
                            </div> -->
                            <div class="col-md-12">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Save</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- /.row (nested) -->
                </div>
                <!-- /.panel-body -->
            </div>
        </div>
        <!-- /.col-lg-12 -->
    </div>
    <!-- /.row -->
</div>
<!-- /.container-fluid -->
