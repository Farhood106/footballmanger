<?php

class SeederRunner
{
    private PDO $db;
    private array $seeders = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function register(string $seeder): self
    {
        $this->seeders[] = $seeder;
        return $this;
    }

    public function run(): void
    {
        foreach ($this->seeders as $seederClass) {
            echo "Running {$seederClass}...\n";
            $seeder = new $seederClass($this->db);
            $seeder->run();
            echo "  Done.\n";
        }
        echo "\nAll seeders completed.\n";
    }
}
