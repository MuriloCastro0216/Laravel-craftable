<?php namespace Brackets\Simpleweb\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SimplewebInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simpleweb:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install a SimpleWEB instance';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Filesystem $files)
    {
        $this->info('Crafting SimpleWEB...');

        $this->publishAllVendors();

        $this->generateUserStuff($files);

        $this->scanAndSaveTranslations();

        $this->frontendAdjustments($files);

        $this->info('SimpleWEB crafted :)');
    }

    private function strReplaceInFile($fileName, $find, $replaceWith) {
        $content = File::get($fileName);
        return File::put($fileName, str_replace($find, $replaceWith, $content));
    }

    private function publishAllVendors() {
        //Spatie Permission
        $this->call('vendor:publish', [
            '--provider' => 'Spatie\\Permission\\PermissionServiceProvider',
            '--tag' => 'migrations'
        ]);
        $this->call('vendor:publish', [
            '--provider' => 'Spatie\\Permission\\PermissionServiceProvider',
            '--tag' => 'config'
        ]);

        //Spatie Backup
        $this->call('vendor:publish', [
            '--provider' => "Spatie\\Backup\\BackupServiceProvider",
        ]);

        //Admin
        $this->call('vendor:publish', [
            '--provider' => "Brackets\\Admin\\AdminServiceProvider",
        ]);

        //Admin Auth
        $this->call('vendor:publish', [
            '--provider' => "Brackets\\AdminAuth\\AdminAuthServiceProvider",
        ]);

        //Admin Translations
        $this->call('vendor:publish', [
            '--provider' => "Brackets\\AdminTranslations\\AdminTranslationsServiceProvider",
        ]);

        //Media
        $this->call('vendor:publish', [
            '--provider' => "Brackets\\Media\\MediaServiceProvider",
        ]);

        //Media
        $this->call('vendor:publish', [
            '--provider' => "Brackets\\Translatable\\TranslatableServiceProvider",
        ]);

        //Simpleweb
        $this->call('vendor:publish', [
            '--provider' => "Brackets\\Simpleweb\\SimplewebServiceProvider",
        ]);
    }

    private function generateUserStuff(Filesystem $files) {
        // Migrate
        $this->call('migrate');

        // Generate User CRUD (with new model)
        $this->call('admin:generate:user', [
            '--model-name' => "App\\Models\\User",
            '--generate-model' => true,
            '--force' => true,
        ]);

        //change config/auth.php to use App/Models/User::class
        $this->strReplaceInFile(config_path('auth.php'),
            "App\\User::class",
            "App\\Models\\User::class");

        // Remove User from App/User
        $files->delete(app_path('User.php'));

        // Generate user profile
        $this->call('admin:generate:user:profile');
    }

    private function scanAndSaveTranslations() {
        // Scan translations
        $this->info('Scanning codebase and storing all translations');

        $this->strReplaceInFile(config_path('admin-translations.php'),
            '// here you can add your own directories',
            '// here you can add your own directories
        // base_path(\'routes\'), // uncomment if you have translations in your routes i.e. you have localized URLs
        base_path(\'vendor/brackets/admin-auth/src\'),
        base_path(\'vendor/brackets/admin-auth/resources\'),');

        $this->call('admin-translations:scan-and-save', [
            'paths' => array_merge(config('admin-translations.scanned_directories'), ['vendor/brackets/admin-auth/src', 'vendor/brackets/admin-auth/resources']),
        ]);
    }

    /**
     * @param Filesystem $files
     */
    private function frontendAdjustments(Filesystem $files) {
        // webpack
        $files->append('webpack.mix.js', "\n\n" . $files->get(__DIR__ . '/../../../install-stubs/webpack.mix.js'));
        $this->info('Webpack configuration updated');

        // register translation assets
        $files->append(resource_path('assets/admin/js/index.js'), "\nimport 'translation';\n");
        $this->info('Admin Translation assets registered');

        // register auth assets
        $files->append(resource_path('assets/admin/js/index.js'), "\nimport 'auth';\n");
        $this->info('Admin Auth assets registered');

        //Change package.json
        $this->info('Changing package.json');
        $packageJsonFile = base_path('package.json');
        $packageJson = $files->get($packageJsonFile);
        $packageJsonContent = json_decode($packageJson, JSON_OBJECT_AS_ARRAY);
        $packageJsonContent['devDependencies']['vee-validate'] = '^2.0.0-rc.13';
        $packageJsonContent['devDependencies']['vue'] = '^2.3.4';
        $packageJsonContent['devDependencies']['vue-flatpickr-component'] = '^2.4.1';
        $packageJsonContent['devDependencies']['vue-js-modal'] = '^1.2.8';
        $packageJsonContent['devDependencies']['vue-multiselect'] = '^2.0.2';
        $packageJsonContent['devDependencies']['vue-notification'] = '^1.3.2';
        $packageJsonContent['devDependencies']['vue-quill-editor'] = '^2.3.0';
        $packageJsonContent['devDependencies']['moment'] = '^2.18.1';
        $packageJsonContent['devDependencies']['vue2-dropzone'] = '^2.3.5';
        $files->put($packageJsonFile, json_encode($packageJsonContent, JSON_PRETTY_PRINT));
        $this->info('package.json changed');
    }
}