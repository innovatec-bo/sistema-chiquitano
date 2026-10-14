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
            <h1 class="page-header">Editar usuario</h1>
        </div>
        <div class="col-md-12">
            <?php
            $this->load->view("default-template/flash-data-basic-messages");
            ?>
        </div>
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    Modifique la informacion del usuario
                </div>

                <div class="panel-body">
					<form role="form" method="post" name="user-add-form" data-parsley-validate>
						<div class="row">
							<div class="col-lg-6">
								<input type="hidden" name="user-id" value="">
								<div class="form-group">
									<label>Nombre</label>
									<input class="form-control" value="<?=set_value('first-name', $user["firstname_usr"])?>" required name="first-name" placeholder="Enter first name">
								</div>
							</div>
							<div class="col-lg-6">
								<div class="form-group">
									<label>Apellido</label>
									<input class="form-control" value="<?=set_value('last-name', $user["lastname_usr"])?>" required name="last-name" placeholder="Enter last name">
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-lg-3">
								<div class="form-group">
									<label>Correo</label>
									<p class="form-control-static"><?=$user["email_usr"]?></p>
								</div>
							</div>
							<div class="col-lg-3">
								<div class="form-group">
									<label>Contraseña</label>
									<input class="form-control" name="password" placeholder="Enter password" id="user-password">
								</div>
							</div>
							<div class="col-lg-3">
								<div class="form-group">
									<label>Confirme contraseña</label>
									<input class="form-control" name="confirm-password" placeholder="Confirm password" data-parsley-equalto="#user-password" data-parsley-equalto-message="Password and confirm password are different">
								</div>
							</div>
							<div class="col-lg-3">
								<div class="form-group">
									<label>Update password</label>
									<div class="checkbox">
										<label>
											<input type="checkbox" name="update-password">Yes
										</label>
									</div>
								</div>
							</div>
						</div>
						<?php
						if($isSuperAdmin == 1)
						{
							?>
							<div class="row">
								<div class="col-lg-3" id="umbo-field">
									<div class="form-group">
										<label>UMBO</label>
										<input class="form-control input-masked" name="umbo" value="<?=$user["umbo_usr"]?>" placeholder="0.00">
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-lg-6">
									<div class="form-group">
										<label>Roles</label>
										<?php
										$html = "";
										$i = 0;
										foreach ($roleList as $role) {
											$role = (array)$role;
											$checked = array_key_exists($role["id_rol"], $userRoleList);
											$parsleyValidation = $i == 0 ? ' required data-parsley-errors-container="#role-error-container" data-parsley-error-message="Choose at least one role" ' : '';
											$html .= '
											<div class="checkbox">
												<label>
													<input type="checkbox" name="roles[]" value="' . $role["id_rol"] . '" ' . $parsleyValidation . ' ' . set_checkbox('roles[]', $role["id_rol"], $checked) . ' >' . $role["rolename_rol"] . '
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
											$checked = in_array($group, $checkedGroups) ? "checked" : "";
											$html .= "
											<div class='checkbox'>
												<label style='font-weight: bold;'>
													<input type='checkbox' name='responsible-group[]' " . $checked . " value='" . html_escape($group) . "'>" . html_escape($group) . "
												</label>
											</div>
											<ol>
											";
											foreach ($statuses as $status)
											{
												$html .= '<li>' . html_escape($status) . '</li>';
											}
											$html .= "</ol>";
										}
										echo $html;
										?>
									</div>
								</div>
							</div>
							<?php
						}
						?>

						<div class="row">
							<div class="col-lg-12">
								<button type="submit" class="btn btn-primary">Save</button>
							</div>
						</div>
					</form>
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
