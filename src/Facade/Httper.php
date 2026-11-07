<?php

declare(strict_types=1);

namespace Youvision\ThinkHttper\Facade;

use think\Facade;

/**
 * @see \Youvision\ThinkHttper\HttperManager
 * @see \Youvision\ThinkHttper\Httper
 *
 * @method static \Youvision\ThinkHttper\Httper client(?string $name = null)
 * @method static \Youvision\ThinkHttper\Httper getDefaultClient()
 * @method static \Youvision\ThinkHttper\HttperManager extend(string $name, callable $factory)
 * @method static \Youvision\ThinkHttper\HttperManager forget(?string $name = null)
 * @method static \Youvision\ThinkHttper\Response get(string $url = '', mixed $data = [], array $options = [])
 * @method static \Youvision\ThinkHttper\Response post(string $url = '', mixed $data = [], array $options = [])
 * @method static \Youvision\ThinkHttper\Response put(string $url = '', mixed $data = [], array $options = [])
 * @method static \Youvision\ThinkHttper\Response patch(string $url = '', mixed $data = [], array $options = [])
 * @method static \Youvision\ThinkHttper\Response delete(string $url = '', mixed $query = [], mixed $data = [], array $options = [])
 * @method static \Youvision\ThinkHttper\Response head(string $url = '', mixed $data = [], array $options = [])
 * @method static \Youvision\ThinkHttper\Response options(string $url = '', mixed $data = [], array $options = [])
 * @method static \Youvision\ThinkHttper\Response request(string $method, string $url = '', mixed $data = [], array $options = [])
 * @method static \Youvision\ThinkHttper\Response download(string $url, string $filename, array $options = [])
 */
class Httper extends Facade
{
    protected static function getFacadeClass()
    {
        return 'httper';
    }
}