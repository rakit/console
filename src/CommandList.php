<?php

namespace Rakit\Console;

class CommandList extends Command
{

    protected $signature = "list {keyword?}";

    protected $description = "Show available commands";

    public function handle($keyword)
    {
        $count = 0;
        $maxLen = 0;
        if ($keyword) {
            $commands = $this->getCommandsLike($keyword);
            $this->writeln(PHP_EOL.$this->color(" Here are commands like '{$keyword}': ", 'blue').PHP_EOL);
        } else {
            $commands = $this->getRegisteredCommands();
            $this->writeln(PHP_EOL.$this->color(" Available Commands: ", 'blue').PHP_EOL);
        }

        ksort($commands);

        foreach(array_keys($commands) as $name) {
            if (strlen($name ) > $maxLen) $maxLen = strlen($name);
        }
        $pad = $maxLen + 3;

        foreach ($commands as $name => $command) {
            $no = ++$count.') ';
            $this->write(str_repeat(' ', 4 - strlen($no)).$this->color($no, 'dark_gray'));
            $this->write($this->color($name, 'green').str_repeat(' ', $pad - strlen($name)));
            $this->writeln($command['description']);
            $this->writeln('');
        }

        $this->writeln(" Type '".$this->color("php ".$this->getFilename()." <command> --help", 'blue')."' for usage information".PHP_EOL);
    }

}
