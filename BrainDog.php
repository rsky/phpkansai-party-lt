<?php

class BrainDog
{
    const OP_ADD = '+';
    const OP_SUB = '-';
    const OP_GT  = '>';
    const OP_LT  = '<';
    const OP_DOT = '.';
    const OP_COM = ',';
    const OP_LBR = '[';
    const OP_RBR = ']';

    protected $tokens = array(
        'わん'     => self::OP_ADD,
        'きゃん'   => self::OP_SUB,
        'わおん'   => self::OP_GT,
        'わーん'   => self::OP_LT,
        'ばう'     => self::OP_DOT,
        'きゃうん' => self::OP_COM,
        'わう'     => self::OP_LBR,
        'きゅーん' => self::OP_RBR,
    );

    protected $handlers = array(
        self::OP_ADD => 'incremant',
        self::OP_SUB => 'decrement',
        self::OP_GT  => 'next',
        self::OP_LT  => 'prev',
        self::OP_DOT => 'putchar',
        self::OP_COM => 'getchar',
    );

    public function bow($code)
    {
        $this->execute($this->parse($this->decode($code)));
    }

    public function parse($code)
    {
        $ary = new BrainDog_OpArray();
        $pst = new SplStack();
        foreach (str_split($code) as $op) {
            $idx = count($ary);
            switch ($op) {
                case self::OP_GT:
                case self::OP_LT:
                case self::OP_ADD:
                case self::OP_SUB:
                case self::OP_DOT:
                case self::OP_COM:
                    $ary[$idx] = new BrainDog_OpCode($op);
                    break;
                case self::OP_LBR:
                    $pst->push($idx);
                    $ary[$idx] = new BrainDog_OpCode($op);
                    break;
                case self::OP_RBR:
                    $pos = $pst->pop(); 
                    $ary[$pos]->jmp = $idx;
                    $ary[$idx] = new BrainDog_OpCode($op, $pos - 1);
                    break;
            }
        }
        return $ary;
    }

    public function execute(BrainDog_OpArray $ary)
    {
        $buf = new BrainDog_Buffer();
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

    public function decode($string)
    {
        $keywords = array_keys($this->tokens);
        usort($keywords, function($a, $b){
            $d = strlen($b) - strlen($a);
            if ($d === 0) {
                return strcmp($b, $a);
            }
            return $d;
        });
        $pattern = '/(' . implode('|', $keywords) . ')/u';
        $code = '';
        if (preg_match_all($pattern, $string, $matches)) {
            foreach ($matches[1] as $key) {
                $code .= $this->tokens[$key];
            }
        }
        return $code;
    }

    public function encode($code)
    {
        $tokens = array_flip($this->tokens);
        $others = array('くーん', '…');
        $string = '';
        foreach (str_split($code) as $op) {
            if (isset($tokens[$op])) {
                $string .= $tokens[$op];
            } elseif (ctype_space($op)) {
                $string .= $op;
            } else {
                $string .= $others[ord($op) % 2];
            }
        }
        return $string;
    }
}

class BrainDog_OpCode
{
    public /* readonly */ $op;
    public /* readonly */ $jmp;

    public function __construct($op = null, $jmp = null)
    {
        $this->op = $op;
        $this->jmp = $jmp;
    }
}

class BrainDog_OpArray extends ArrayObject
{
    public function append($op)
    {
        if (!$op instanceof BrainDog_OpCode) {
            throw new InvalidArgumentException();
        }
        parent::append($op);
    }

    public function offsetSet($offset, $op)
    {
        if (is_null($offset)) {
            $this->append($op);
        }
        if (!(is_int($offset) && $offset >= 0)) {
            throw new InvalidArgumentException();
        }
        if (!$op instanceof BrainDog_OpCode) {
            throw new InvalidArgumentException();
        }
        parent::offsetSet($offset, $op);
    }
}

class BrainDog_EOFException extends RuntimeException
{
}

class BrainDog_Buffer
{
    protected $buf;
    protected $pos;
    protected $dst;
    protected $src;

    public function __construct(
        SplFileObject $output = null,
        SplFileObject $input = null)
    {
        $this->buf = array(0);
        $this->pos = 0;
        if (is_null($output)) {
            $this->dst = new SplFileObject('php://stdout', 'wb');
        } else {
            $this->dst = $output;
        }
        if (is_null($input)) {
            $this->src = new SplFileObject('php://stdin', 'rb');
        } else {
            $this->src = $input;
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
            throw new BrainDog_EOFException();
        }
    }
}
