<?php


use Uselagoon\Sailinglagoon\Commands\SailinglagoonCommand;

it('will allow us to remove unused services', function () {
    $sailingLagoonCommand = new SailinglagoonCommand();

    $serviceList = collect(["cli", "php", "mariadb"]);
    $services = [
      "first" => [
          "depends_on" => ["cli", "php", "shouldntexist"]
      ]
    ];

    $newServices = $sailingLagoonCommand->removeUnusedServiceDependencies($services, $serviceList);

    expect($newServices["first"]["depends_on"])->not()->toContain("shouldntexist");
    expect($newServices["first"]["depends_on"])->toContain("cli");
});
