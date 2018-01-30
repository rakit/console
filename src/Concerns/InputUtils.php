<?php

namespace Rakit\Console\Concerns;

trait InputUtils
{
    protected $questionSuffix = "\n> ";

    /**
     * Asking question
     *
     * @param string $message
     * @param string $fgColor
     * @param string $bgColor
     */
    public function ask($question, $default = null)
    {
        if ($default) {
            $question = $question. ' ' .$this->color("[{$default}]", 'green');
        }

        $this->write($question.$this->questionSuffix, 'blue');

        $handle = fopen("php://stdin", "r");
        $answer = trim(fgets($handle));
        fclose($handle);
        return $answer;
    }

    /**
     * Asking secret question
     *
     * @param string $message
     * @param string $fgColor
     * @param string $bgColor
     */
    public function askSecret($question, $default)
    {
        if ($default) {
            $question = $question. ' ' .$this->color("[{$default}]", 'green');
        }

        $this->write($question.$this->questionSuffix);

        if ($this->isWindows()) {
            $exe = __DIR__.'/../../bin/hiddeninput.exe';
            // handle code running from a phar
            if ('phar:' === substr(__FILE__, 0, 5)) {
                $tmpExe = sys_get_temp_dir().'/hiddeninput.exe';
                copy($exe, $tmpExe);
                $exe = $tmpExe;
            }
            $value = rtrim(shell_exec($exe));
            $this->writeln('');
            if (isset($tmpExe)) {
                unlink($tmpExe);
            }
            return $value;
        }

        if ($this->hasSttyAvailable()) {
            $sttyMode = shell_exec('stty -g');
            shell_exec('stty -echo');
            $handle = fopen("php://stdin", "r");
            $value = fgets($handle, 4096);
            shell_exec(sprintf('stty %s', $sttyMode));
            fclose($handle);
            if (false === $value) {
                throw new RuntimeException('Aborted');
            }
            $value = trim($value);
            $this->writeln('');
            return $value;
        }

        if (false !== $shell = $this->getShell()) {
            $readCmd = $shell === 'csh' ? 'set mypassword = $<' : 'read -r mypassword';
            $command = sprintf("/usr/bin/env %s -c 'stty -echo; %s; stty echo; echo \$mypassword'", $shell, $readCmd);
            $value = rtrim(shell_exec($command));
            $this->writeln('');
            return $value;
        }

        throw new RuntimeException('Unable to hide the response.');
    }

    /**
     * Input confirmation
     *
     * @param string $message
     * @param string $fgColor
     * @param string $bgColor
     */
    public function confirm($question, $default = false)
    {
        $availableAnswers = [
            'yes' => true,
            'no' => false,
            'y' => true,
            'n' => false
        ];

        $result = null;
        do {
            if ($default) {
                $suffix = $this->color("[", 'dark_gray').$this->color("Y", "green").$this->color("/n]", 'dark_gray');
            } else {
                $suffix = $this->color("[y/", 'dark_gray').$this->color("N", "green").$this->color("]", 'dark_gray');
            }
            $answer = $this->ask($question.' '.$suffix) ?: ($default ? 'y' : 'n');

            if (!isset($availableAnswers[$answer])) {
                $this->writeln('Please type: y, n, yes, or no.', 'red');
            } else {
                $result = $availableAnswers[$answer];
            }
        } while (is_null($result));

        return $availableAnswers[$answer];
    }

}
