<?php

namespace Uselagoon\Sailinglagoon\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\File;

use function Illuminate\Filesystem\join_paths;

class SailinglagoonCommand extends Command
{

    protected $dockerComposeName = "lagoon-docker-compose.yml";

    /** @var string[] $services contains a list of currently supported service types */
    protected $services = [
        'mysql',
        'pgsql',
        'mariadb',
        'redis',
        'memcached',
        'meilisearch',
        'typesense',
        'minio',
        'mailpit',
        'selenium',
        'soketi',
    ];

    /** @var string[] $unsupportedServices for development, we'll focus on the service we most use */
    protected $unsupportedServices = [
        'typesense' => '',
        'minio' => '',
        'mailpit' => '',
        'selenium' => '',
        'soketi' => '',
        'memcached' => 'On Lagoon we encourage the adoption of Redis as a caching backend',
    ];

    /** @var string[] $defaultServices keeps track of services in lagoon that should always exist for Laravel installations */
    protected $defaultServices = [
        'cli',
        'php',
        'nginx',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sail:onlagoon {--projectName=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This will read your docker-compose.yaml file and attempt to generate the required files for a Lagoon installation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dockerComposeFile = base_path("docker-compose.yml");
        $parsedCompose = Yaml::parseFile($dockerComposeFile);

        // We go through the services defined here and see if we can match them with existing definitions.
        if (!key_exists("services", $parsedCompose)) {
            throw new \Exception(
                "Invalid structure to docker-compose.yml, no services found"
            );
        }

        // ensure that the user has set their options
        $projectName = $this->option('projectName');
        if(empty($projectName)) {
            $projectName = $this->ask("Please enter a project name", "my-project");
        }

        $services = collect(array_keys($parsedCompose['services']))->merge($this->defaultServices);

        // Here we ensure that none of the incoming services aren't on our unsupported list
        $disallowedServices = array_intersect(array_keys($parsedCompose['services']), array_keys($this->unsupportedServices));
        if(count($disallowedServices) > 0) {
            $this->info("The following unsupported services have been detected:");
            foreach ($disallowedServices as $key) {
                $this->info(sprintf("* %s: %s", $key, $this->unsupportedServices[$key]));
            }
            if(!$this->confirm("Continue Lagoonizing while ignoring these services?", true)) {
                $this->error("Will not continue");
                return 1;
            }
        }

        //Let's build the service list and then parse
        $stubsRootPath = join_paths(__DIR__, "sailingLagoonAssets/stubs");
        $yamlFile = file_get_contents(join_paths($stubsRootPath, "docker-compose.stub"));

        $dockerComposeFile = $this->generateDockerCompose($services, $stubsRootPath, $yamlFile);

        if(!file_put_contents(join_paths(base_path(), $this->dockerComposeName), Yaml::dump($dockerComposeFile,5))) {
            throw new \Exception("Unable to write docker-compose file");
        }

        $this->info(sprintf("Successfully created %s", $this->dockerComposeName));

        // Let's now do the same for env stubs

        $stubsRootPath = join_paths(__DIR__, "sailingLagoonAssets/envstubs");
        $stubContents = "";
        foreach ($services as $serviceName) {
            $stubPath = join_paths($stubsRootPath, $serviceName.".stub");
            if(file_exists($stubPath)) {
                $stubContents .= sprintf("\n## %s\n\n%s\n", $serviceName, file_get_contents($stubPath));
            }
        }
        if(!empty($stubContents)) {
            file_put_contents(join_paths(base_path(), ".lagoon.env"), $stubContents);
        }

        // now let's copy the files we require to build everything to .lagoon
        $copySource = join_paths(__DIR__, "sailingLagoonAssets", "Lagoon");
        $copyDest = join_paths(base_path(), "lagoon");

        if(File::copyDirectory($copySource, $copyDest)) {
            $this->info("Successfully copied Lagoon assets to .lagoon");
        }

        // Let's generate the .lagoon.yml file
        $lagoonYml = file_get_contents(join_paths(__DIR__, "sailingLagoonAssets",".lagoon.yml"));
        $replacements = [
          '%projectName%' => $projectName,
        ];

        $lagoonYml = str_replace(array_keys($replacements), $replacements, $lagoonYml);


        file_put_contents(join_paths(base_path(), ".lagoon.yml"), $lagoonYml);

        return true;
    }

    /**
     * @param $services
     * @param Collection $existentServices
     * @return array
     */
    public function removeUnusedServiceDependencies($services, Collection $existentServices): array
    {
        foreach ($services as $serviceName => &$service) {
            if (key_exists("depends_on", $service)) {
                $dependsOnNew = [];
                for ($i = 0; $i < count($service["depends_on"]); $i++) {
                    if (in_array($service["depends_on"][$i], $existentServices->toArray())) {
                        $dependsOnNew[] = $service["depends_on"][$i];
                    }
                }
                if (count($dependsOnNew) > 0) {
                    $service["depends_on"] = $dependsOnNew;
                } else {
                    unset($service["depends_on"]);
                }
            }
        }
        return $services;
    }

    /**
     * @param Collection $services
     * @param string $stubsRootPath
     * @param string $yamlFile
     * @param mixed $dockerComposeFile
     * @return array
     */
    public function generateDockerCompose(Collection $services, string $stubsRootPath, string $yamlFile): mixed
    {
        foreach ($services as $serviceName) {
            $stubPath = join_paths($stubsRootPath, $serviceName . ".stub");
            if (file_exists($stubPath)) {
                $yamlFile .= file_get_contents($stubPath);
            }
        }
        $dockerComposeFile = Yaml::parse($yamlFile);
        // now we go through the services and remove any depends on that doesn't appear in our total service list
        $serviceList = $dockerComposeFile["services"];
        $dockerComposeFile["services"] = self::removeUnusedServiceDependencies($serviceList, $services);
        return $dockerComposeFile;
    }

}
