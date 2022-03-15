<?php

declare(strict_types=1);

/*
 * @copyright 2022 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2022 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Calendar\Listener;

use OCA\Calendar\Events\AppointmentBookedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IServerContainer;
use OCP\IUserManager;
use OCP\Talk\IBroker;
use Psr\Log\LoggerInterface;
use Throwable;
use function interface_exists;

class AppointmentBookedListener implements IEventListener {

	/** @var IServerContainer */
	private $container;

	/** @var IUserManager */
	private $userManager;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(IServerContainer $container,
								IUserManager $userManager,
								LoggerInterface $logger) {
		$this->container = $container;
		$this->userManager = $userManager;
		$this->logger = $logger;
	}

	public function handle(Event $event): void {
		if (!($event instanceof AppointmentBookedEvent)) {
			// Don't care
			return;
		}

		if (!$event->getConfig()->getCreateTalkRoom()) {
			$this->logger->debug('Booked appointment of config {config} does not need a Talk room', [
				'config' => $event->getConfig()->getId(),
			]);
			return;
		}

		// TODO: remove version check with 24+
		if (!interface_exists(IBroker::class)) {
			// API isn't there yet

			return;
		}

		try {
			/** @var IBroker $broker */
			$broker = $this->container->get(IBroker::class);
		} catch (Throwable $e) {
			$this->logger->error('Could not get Talk broker: ' . $e->getMessage(), [
				'exception' => $e,
			]);
			return;
		}

		if (!$broker->hasBackend()) {
			$this->logger->warning('Can not create Talk room for config {config} because there is no backend', [
				'config' => $event->getConfig()->getId(),
			]);
			return;
		}

		$organizer = $this->userManager->get($event->getConfig()->getUserId());
		if ($organizer === null) {
			$this->logger->error('Could not find appointment owner {uid}', [
				'uid' => $event->getConfig()->getUserId(),
			]);
			return;
		}
		$broker->createConversation(
			$event->getConfig()->getName(),
			[$organizer],
			$broker->newConversationOptions()->setPublic(false),
		);
	}

}
