<?php

/*
 * PHPacto - Contract testing solution
 *
 * Copyright (c) Damian Długosz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace PHPacto\MockServer\Delivery;

use Bigfoot\PHPacto\Logger\Logger;
use Bigfoot\PHPacto\Matcher\Mismatches\Mismatch;
use Bigfoot\PHPacto\Matcher\Mismatches\MismatchCollection;
use Bigfoot\PHPacto\PactInterface;
use Http\Factory\Discovery\HttpFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Mock implements RequestHandlerInterface
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var PactInterface[]
     */
    private $pacts;

    /**
     * @var string
     */
    private $contractLocation;

    public function __construct(Logger $logger, array $pacts)
    {
        $this->logger = $logger;
        $this->pacts = $pacts;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $uri = HttpFactory::uriFactory()->createUri()
            ->withPath($request->getUri()->getPath())
            ->withQuery($request->getUri()->getQuery());

        $pact = $this->findMatchingPact($request->withUri($uri));

        return $pact->getResponse()->getSample()
            ->withAddedHeader('PHPacto-Contract', $this->contractLocation);
    }

    private function findMatchingPact(RequestInterface $request): PactInterface
    {
        $mismatches = [];

        foreach ($this->pacts as $contractLocation => $pact) {
            try {
                $pact->getRequest()->assertMatch($request);

                $this->logger->log(sprintf('Found matching contract %s', $contractLocation));

                $this->contractLocation = $contractLocation;

                return $pact;
            } catch (Mismatch $mismatch) {
                // This Pact isn't matching, try next.
                $mismatches[$contractLocation] = $mismatch;
            }
        }

        throw new MismatchCollection($mismatches, 'No matching contract found for your request');
    }
}
