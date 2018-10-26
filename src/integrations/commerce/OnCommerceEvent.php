<?php
/**
 * Bookings plugin for Craft CMS 3.x
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) Ether Creative
 */

namespace ether\bookings\integrations\commerce;

use craft\commerce\elements\Order;
use craft\commerce\events\LineItemEvent;
use craft\commerce\events\RefundTransactionEvent;
use craft\commerce\models\LineItem;
use craft\helpers\Db;
use ether\bookings\Bookings;
use ether\bookings\elements\BookedTicket;
use ether\bookings\elements\Booking;
use ether\bookings\helpers\DateHelper;
use ether\bookings\records\BookedSlotRecord;
use yii\base\Event;


/**
 * Class OnCommerceEvent
 *
 * @author  Ether Creative
 * @package ether\bookings\integrations\commerce
 * @since   1.0.0
 */
class OnCommerceEvent
{

	// Orders
	// =========================================================================

	/**
	 * @param Event $event
	 */
	public function onBeforeSaveLineItem (Event $event)
	{
		$bookings = Bookings::getInstance();

		/** @var LineItem $lineItem */
		$lineItem = $event->sender;

		/** @var Order $order */
		$order = $lineItem->order;

//		/** @var bool $isNew */
//		$isNew = !$lineItem->id;

		$options = $lineItem->getOptions();

		// Ensure this is a booking line item
		$ticketId =
			array_key_exists('ticketId', $options)
				? $options['ticketId']
				: false;

		if (!$ticketId)
			return;

		$startDate = DateHelper::parseDateFromPost($options['ticketDate']);
		// TODO: Date ranges
		$endDate = null;

		$ticket = $bookings->tickets->getTicketById($ticketId);

		// Does the ticket exist?
		if ($ticket === null)
		{
			$err = \Craft::t('bookings', 'Unable to find ticket for the given ID.');

			$order->addError('ticket', $err);
			$lineItem->addError('ticket', $err);

			return;
		}

		$event = $ticket->getEvent();

		// Is time valid?
		// TODO: Date ranges
		if ($event->isDateOccurrence($startDate) === false)
		{
			$err = \Craft::t('bookings', 'Selected Date / Time is invalid.');

			$order->addError('ticket', $err);
			$lineItem->addError($ticket->getField()->handle, $err);

			return;
		}

		// Do we have an existing booking?
		// TODO: Date ranges
		$booking = $bookings->bookings->getBookingByOrderEventAndSlot(
			$order->id,
			$event->id,
			$startDate
		);

		// Is time available?
		// TODO: Date ranges
		$qty = $lineItem->qty;

		if ($qty > 0 && $bookings->availability->isTimeAvailable($booking, $ticket, $startDate, $qty) === false)
		{
			$err = \Craft::t(
				'bookings',
				$qty > 1
					? 'Selected Date / Time is unavailable at that quantity.'
					: 'Selected Date / Time is unavailable.'
			);

			$order->addError('ticket', $err);
			$lineItem->addError($ticket->getField()->handle, $err);

			return;
		}
	}

	/**
	 * @param LineItemEvent $lineItemEvent
	 *
	 * @throws \Throwable
	 */
	public function onAfterSaveLineItem (LineItemEvent $lineItemEvent)
	{
		$craft = \Craft::$app;
		$bookings = Bookings::getInstance();

		/** @var LineItem $lineItem */
		$lineItem = $lineItemEvent->lineItem;

		/** @var Order $order */
		$order = $lineItem->order;

		/** @var bool $isNew */
		$isNew = $lineItemEvent->isNew;

		$options = $lineItem->getOptions();

		// Ensure this is a booking line item
		$ticketId =
			array_key_exists('ticketId', $options)
				? $options['ticketId']
				: false;

		if (!$ticketId)
			return;

		$startDate = DateHelper::parseDateFromPost($options['ticketDate']);
		// TODO: Date ranges
		$endDate = null;
//		$endDate = $craft->request->getBodyParam('ticketEndDate');

		$ticket = $bookings->tickets->getTicketById($ticketId);

		$event = $ticket->getEvent();

		// Do we have an existing booking?
		// TODO: cache from onBeforeSaveLineItem?
		// TODO: Date ranges
		$booking = $bookings->bookings->getBookingByOrderEventAndSlot(
			$order->id,
			$event->id,
			$startDate
		);

		// Create a new booking if one doesn't exist
		if ($booking === null)
		{
			$booking = new Booking();

			$booking->status        = Booking::STATUS_RESERVED;
			$booking->eventId       = $event->id;
			$booking->orderId       = $order->id;
			$booking->customerId    = $order->customerId;
			$booking->customerEmail = $order->email;
			$booking->slot          = $startDate;

			$craft->elements->saveElement($booking);
		}

		// Clear any existing booked tickets (will Cascade to slots) if we're updating
		if ($isNew === false)
		{
			$bookedTickets = BookedTicket::findAll([
				'ticketId'   => $ticket->id,
				'bookingId'  => $booking->id,
				'lineItemId' => $lineItem->id,
				'startDate'  => $startDate,
				'endDate'    => $endDate,
			]);

			foreach ($bookedTickets as $bookedTicket)
				$craft->elements->deleteElement($bookedTicket);
		}

		// Create the booked tickets
		$bookedTickets = [];

		$i = $lineItem->qty;
		while ($i--)
		{
			$bookedTicket = new BookedTicket();

			$bookedTicket->ticketId = $ticket->id;
			$bookedTicket->bookingId = $booking->id;
			$bookedTicket->lineItemId = $lineItem->id;
			$bookedTicket->startDate = $startDate;
			$bookedTicket->endDate = $endDate;

			$craft->elements->saveElement($bookedTicket);
			$bookedTickets[] = $bookedTicket;
		}

		// Create the slots for this ticket
		$slots = $bookings->slots->generateSlotsForGivenTimes($event, $startDate, $endDate);
		$slotsCount = count($slots);

		foreach ($bookedTickets as $bookedTicket)
		{
			$i = 0;

			foreach ($slots as $slot)
			{
				$bookedSlot = new BookedSlotRecord();

				$bookedSlot->start = $i === 0;
				$bookedSlot->end = ++$i === $slotsCount;
				$bookedSlot->eventId = $event->id;
				$bookedSlot->ticketId = $ticket->id;
				$bookedSlot->bookingId = $booking->id;
				$bookedSlot->bookedTicketId = $bookedTicket->id;
				$bookedSlot->date = Db::prepareDateForDb($slot);

				$bookedSlot->save();
			}
		}
	}

	/**
	 * @param Event $event
	 *
	 * @throws \Throwable
	 */
	public function onComplete (Event $event)
	{
		/** @var Order $order */
		$order = $event->sender;

		$bookings = Bookings::getInstance()->bookings->getBookingsByOrderId($order->id);

		/** @var Booking $booking */
		foreach ($bookings as $booking)
			$booking->markAsComplete();
	}

	// Payments
	// =========================================================================

	public function onRefund (RefundTransactionEvent $event)
	{
		$order = $event->transaction->order;

		$bookings = Bookings::getInstance()->bookings->getBookingsByOrderId($order->id);

		/** @var Booking $booking */
		foreach ($bookings as $booking)
			$booking->expireBooking();
	}

}