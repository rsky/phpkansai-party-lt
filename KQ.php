<?php
namespace KQ;

class Driver
{
    const DOOR_WILL_CLOSE = 'ﾀﾞｧｼｴﾘｲｪｽ';

    const OP_ADD = '+';
    const OP_SUB = '-';
    const OP_GT  = '>';
    const OP_LT  = '<';
    const OP_DOT = '.';
    const OP_COM = ',';
    const OP_LBR = '[';
    const OP_RBR = ']';

    protected $tokens = array(
        'ﾀﾞｧﾀﾞｧ' => self::OP_ADD,
        'ｼｴﾘｼｴﾘ' => self::OP_SUB,
        'ﾀﾞｧｲｪｽ' => self::OP_GT,
        'ｲｪｽﾀﾞｧ' => self::OP_LT,
        'ｼｴﾘﾀﾞｧ' => self::OP_DOT,
        'ﾀﾞｧｼｴﾘ' => self::OP_COM,
        'ｼｴﾘｲｪｽ' => self::OP_LBR,
        'ｲｪｽｼｴﾘ' => self::OP_RBR,
    );

    protected $handlers = array(
        self::OP_ADD => 'incremant',
        self::OP_SUB => 'decrement',
        self::OP_GT  => 'next',
        self::OP_LT  => 'prev',
        self::OP_DOT => 'putchar',
        self::OP_COM => 'getchar',
    );

    protected $pattern;

    public function __construct()
    {
        $keywords = array_keys($this->tokens);

        usort($keywords, function($a, $b){
            $d = strlen($b) - strlen($a);
            if ($d === 0) {
                return strcmp($b, $a);
            }
            return $d;
        });

        $keywords = array_map(function($keyword){
            return preg_quote($keyword, '/');
        }, $keywords);

        $this->pattern = '/(' . implode('|', $keywords) . ')/u';
    }

    public function doorWillClose($code)
    {
        $out = new \SplFileObject('php://temp', 'wb+');
        $buf = new IO(null, $out);
        $this->execute($this->parse($this->decode($code)), $buf);

        $result = '';
        foreach ($out as $line) {
            $result .= $line;
        }
        return $result;
    }

    public function parse($code)
    {
        $ary = new OpArray();
        $pst = new \SplStack();
        foreach (str_split($code) as $op) {
            $idx = count($ary);
            switch ($op) {
                case self::OP_GT:
                case self::OP_LT:
                case self::OP_ADD:
                case self::OP_SUB:
                case self::OP_DOT:
                case self::OP_COM:
                    $ary[$idx] = new OpCode($op);
                    break;
                case self::OP_LBR:
                    $pst->push($idx);
                    $ary[$idx] = new OpCode($op);
                    break;
                case self::OP_RBR:
                    $pos = $pst->pop();
                    $ary[$pos]->jmp = $idx;
                    $ary[$idx] = new OpCode($op, $pos - 1);
                    break;
            }
        }
        return $ary;
    }

    public function execute(OpArray $ary, IO $buf = null)
    {
        if (is_null($buf)) {
            $buf = new IO();
        }

        for ($pos = 0; isset($ary[$pos]); $pos++) {
            $code = $ary[$pos];
            switch ($code->op) {
                case self::OP_GT:
                case self::OP_LT:
                case self::OP_ADD:
                case self::OP_SUB:
                case self::OP_DOT:
                case self::OP_COM:
                    $buf->{$this->handlers[$code->op]}();
                    break;
                case self::OP_LBR:
                    if ($buf->current() === 0) {
                        $pos = $code->jmp;
                    }
                    break;
                case self::OP_RBR:
                    $pos = $code->jmp;
                    break;
                default:
                    return;
            }
        }
    }

    public function decode($string, $normalize = true)
    {
        if ($normalize) {
            $string = mb_convert_kana($string, 'aks', 'UTF-8');
        }

        $chars = '/[^' . self:: DOOR_WILL_CLOSE . ']/u';
        $string = preg_replace($chars, '', $string);
        if (mb_strlen($string) % 6 !== 0) {
            $err = 'invalid character(s) found';
            throw new \InvalidArgumentException($err);
        }

        $code = '';
        if (preg_match_all($this->pattern, $string, $matches)) {
            foreach ($matches[1] as $key) {
                $code .= $this->tokens[$key];
            }
        }

        return $code;
    }

    public function encode($code, $auto_exc = true)
    {
        $tokens = array_flip($this->tokens);
        $string = '';
        foreach (str_split($code) as $offset => $op) {
            if (isset($tokens[$op])) {
                $string .= $tokens[$op];
                if ($auto_exc) {
                    switch ($op) {
                        case self::OP_GT:
                        case self::OP_ADD:
                        case self::OP_DOT:
                        case self::OP_COM:
                            $string .= '!!';
                            break;
                        default:
                            $string .= '!';
                    }
                }
            } elseif (preg_match('/[\\s!]/', $op)) {
                $string .= $op;
            } else {
                $err = sprintf('undefined operator 0x%02x at offset %d',
                               ord($op), $offset);
                throw new UndefinedOperatorException($err);
            }
        }
        return $string;
    }
}

class OpCode
{
    public /* readonly */ $op;
    public /* readonly */ $jmp;

    public function __construct($op = null, $jmp = null)
    {
        $this->op = $op;
        $this->jmp = $jmp;
    }
}

class OpArray extends \ArrayObject
{
    public function append($op)
    {
        if (!$op instanceof OpCode) {
            throw new \InvalidArgumentException();
        }
        parent::append($op);
    }

    public function offsetSet($offset, $op)
    {
        if (is_null($offset)) {
            $this->append($op);
        }
        if (!(is_int($offset) && $offset >= 0)) {
            throw new \OutOfBoundsException();
        }
        if (!$op instanceof OpCode) {
            throw new \InvalidArgumentException();
        }
        parent::offsetSet($offset, $op);
    }
}

class IO
{
    protected $buf;
    protected $pos;
    protected $dst;
    protected $src;

    public function __construct(\SplFileObject $input = null,
                                \SplFileObject $output = null)
    {
        $this->buf = array(0);
        $this->pos = 0;
        if (is_null($input)) {
            $this->src = new \SplFileObject('php://stdin', 'rb');
        } else {
            $this->src = $input;
        }
        if (is_null($output)) {
            $this->dst = new \SplFileObject('php://stdout', 'wb');
        } else {
            $this->dst = $output;
        }
    }

    public function current()
    {
        if (!isset($this->buf[$this->pos])) {
            $this->buf[$this->pos] = 0;
        }
        return $this->buf[$this->pos];
    }

    public function next()
    {
        $this->pos++;
        return $this->current();
    }

    public function prev()
    {
        $this->pos--;
        return $this->current();
    }

    public function incremant()
    {
        $value = $this->current() + 1;
        $this->buf[$this->pos] = $value;
        return $value;
    }

    public function decrement()
    {
        $value = $this->current() - 1;
        $this->buf[$this->pos] = $value;
        return $value;
    }

    public function putchar()
    {
        $this->dst->fwrite(chr($this->current()));
    }

    public function getchar()
    {
        $this->buf[$this->pos] = ord($this->src->fgetc());
        if ($this->src->eof()) {
            throw new EOFException();
        }
    }
}

class EOFException extends \RuntimeException
{
}

class UndefinedOperatorException extends \LogicException
{
}
