<?php
namespace Isti\CsCartInstaller\Console;

use GuzzleHttp\Client;
use RuntimeException;
use ZipArchive;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DownloadCommand extends Command {

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure() {
        $this
          ->setName('download')
          ->setDescription('Download the latest CS-Cart.')
          ->addArgument('folder', InputArgument::OPTIONAL, 'If defined, CS-Cart will downloaded to that folder, otherwise it will be downloaded to the current working directory.')
          ->addOption('mve', NULL, InputOption::VALUE_NONE, 'Download the Multi-Vendor edition instead of Ultimate');
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface $input
     * @param  OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The Zip PHP extension is not installed. Please install it and try again.');
        }
        $this->verifyApplicationDoesntExist(
          $directory = ($input->getArgument('folder')) ? getcwd() . '/' . $input->getArgument('folder') : getcwd(),
          $output
        );

        $version = $this->getVersion($input);

        $output->writeln("<info>Downloading CS-Cart {$version}</info>");


        $this->download($zipFile = $this->makeFilename(), $version)
          ->extract($zipFile, $directory)
          ->cleanUp($zipFile);

        $output->writeln('<comment>Download completed! You can start installing.</comment>');
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string $directory
     * @param  OutputInterface $output
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory, OutputInterface $output) {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename() {
        return getcwd() . '/cscart_' . md5(time() . uniqid()) . '.zip';
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @param  string $zipFile
     * @param  string $version
     * @return $this
     */
    protected function download($zipFile, $version = 'ultimate') {
        switch ($version) {
            case 'Ultimate':
                $url = 'https://www.cs-cart.com/index.php?dispatch=pages.get_trial&page_id=297&edition=ultimate';
                break;
            case 'Multi-Vendor':
                $url = 'https://www.cs-cart.com/index.php?dispatch=pages.get_trial&page_id=297&edition=multivendor';
                break;
        }
        $response = (new Client)->get($url);
        file_put_contents($zipFile, $response->getBody());
        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     *
     * @param  string $zipFile
     * @param  string $directory
     * @return $this
     */
    protected function extract($zipFile, $directory) {
        $archive = new ZipArchive;
        $archive->open($zipFile);
        $archive->extractTo($directory);
        $archive->close();
        return $this;
    }

    /**
     * Clean-up the Zip file.
     *
     * @param  string $zipFile
     * @return $this
     */
    protected function cleanUp($zipFile) {
        @chmod($zipFile, 0777);
        @unlink($zipFile);
        return $this;
    }

    /**
     * Get the version that should be downloaded.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface $input
     * @return string
     */
    protected function getVersion($input) {
        if ($input->getOption('mve')) {
            return 'Multi-Vendor';
        }
        return 'Ultimate';
    }
}