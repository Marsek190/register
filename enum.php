<?php

abstract class Enum
{ 
    public function match(string $className): bool
    {
        return get_class($this) === $className;
    }
}

abstract class DaysOfWeek extends Enum { }

final class Sunday extends DaysOfWeek { }
final class Monday extends DaysOfWeek { }
// ...

$s = new Sunday();

echo !$s->match(Monday::class);


switch (true) {
    case $s instanceof Monday:
	      break;
}
