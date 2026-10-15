<?php
declare(strict_types=1);

final class cdm extends controller
{
    public function index(array $params = []): void
    {
        $this->require_admin(7);
        header('Location: /admin/cdm');
        exit;
    }

    public function admin(array $params = []): void
    {
        $this->require_admin(7);
        /** @var cdm_model $model */
        $model = $this->model('cdm_model');
        $modules = $model->modules();
        $report = null;
        $error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();
            $name = trim((string) ($_POST['module'] ?? ''));
            try {
                $report = $model->diagnose($name);
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
        $this->view('admin/index', [
            'modules' => $modules,
            'report' => $report,
            'error' => $error,
        ]);
    }
}