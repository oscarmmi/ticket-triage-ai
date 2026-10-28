<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SupportTicket::query()->delete();

        $tickets = [
            [
                'subject' => 'Duplicate charge after checkout',
                'body' => 'I see two charges on my statement for one order. One of them is still pending, but I want to make sure I will not be billed twice. If this is not resolved, I may need to dispute it with my bank as a chargeback.',
                'channel' => 'email', 'customer_name' => 'Morgan Lee', 'category' => 'billing', 'priority' => 'urgent', 'confidence' => 0.98,
                'reply' => 'Hi Morgan, I’m sorry for the duplicate charge concern. I’ve flagged this for an urgent billing review. One entry may be a temporary authorization, but our team will verify both transactions and follow up with you.',
                'status' => 'open', 'featured' => true,
            ],
            [
                'subject' => 'Tracking has not moved in four days',
                'body' => 'My package tracking still shows “label created” and there have been no updates since Monday. Can you check whether it has shipped?',
                'channel' => 'chat', 'customer_name' => 'Avery Brooks', 'category' => 'shipping', 'priority' => 'medium', 'confidence' => 0.94,
                'reply' => 'Hi Avery, thanks for checking in. Please share your order number and I’ll look up the latest carrier scan and expected delivery date for you.',
                'status' => 'open', 'featured' => true,
            ],
            [
                'subject' => 'App crashes when I export a report',
                'body' => 'Every time I tap Export on the monthly report, the app crashes and I lose my filters. It started after the latest update.',
                'channel' => 'email', 'customer_name' => 'Jordan Kim', 'category' => 'product_bug', 'priority' => 'high', 'confidence' => 0.96,
                'reply' => 'Hi Jordan, I’m sorry the latest update is interrupting your reports. I’ve shared this with our product team. Could you send your device model and app version so we can reproduce the crash?',
                'status' => 'open', 'featured' => true,
            ],
            [
                'subject' => 'Password reset email never arrives',
                'body' => 'I have tried the reset link a few times but nothing has landed in my inbox or spam folder. I cannot sign in to my account.',
                'channel' => 'chat', 'customer_name' => 'Taylor Reed', 'category' => 'account', 'priority' => 'high', 'confidence' => 0.91,
                'reply' => 'Hi Taylor, I’m sorry you’re locked out. Please confirm the email address on the account and we can help check the reset delivery. In the meantime, please check any inbox filters for Harbor Desk.',
                'status' => 'open', 'featured' => false,
            ],
            [
                'subject' => 'What are your support hours?',
                'body' => 'Quick question: what hours is the Harbor Desk support team available during the week?',
                'channel' => 'email', 'customer_name' => 'Casey Patel', 'category' => 'other', 'priority' => 'low', 'confidence' => 0.99,
                'reply' => 'Hi Casey, Harbor Desk is available Monday through Friday, 9:00 AM to 5:00 PM ET. We look forward to helping whenever you need us.',
                'status' => 'triaged', 'featured' => false,
            ],
            [
                'subject' => 'Invoice does not match my plan',
                'body' => 'The invoice I received is higher than the monthly plan shown in my account. Could someone explain the extra line item?',
                'channel' => 'email', 'customer_name' => 'Riley Chen', 'category' => 'billing', 'priority' => 'high', 'confidence' => 0.92,
                'reply' => 'Hi Riley, thanks for flagging the invoice. We’ll review the line item against your current plan and get back to you with a clear breakdown. Please include the invoice number if you have it handy.',
                'status' => 'triaged', 'featured' => false,
            ],
            [
                'subject' => 'Package delivered to the wrong building',
                'body' => 'The carrier marked my package as delivered, but the photo shows a different entrance. I checked with my neighbors and still cannot find it.',
                'channel' => 'phone', 'customer_name' => 'Jamie Rivera', 'category' => 'shipping', 'priority' => 'high', 'confidence' => 0.88,
                'reply' => 'Hi Jamie, I’m sorry your delivery has gone missing. We’ll start a carrier trace right away. Please confirm your delivery ZIP code and order number so we can investigate.',
                'status' => 'triaged', 'featured' => false,
            ],
            [
                'subject' => 'Cannot update my profile photo',
                'body' => 'The save button spins forever whenever I try to change my profile photo. I have tried two different browsers.',
                'channel' => 'chat', 'customer_name' => 'Drew Ellis', 'category' => 'product_bug', 'priority' => 'medium', 'confidence' => 0.83,
                'reply' => 'Hi Drew, thanks for reporting this. Our team will look into the profile photo save issue. If possible, please share the browser names and approximate image size you tried.',
                'status' => 'triaged', 'featured' => false,
            ],
            [
                'subject' => 'Please change the email on my account',
                'body' => 'I no longer have access to my old work email and need to update the email address on my profile. What do you need from me?',
                'channel' => 'email', 'customer_name' => 'Sam Okafor', 'category' => 'account', 'priority' => 'medium', 'confidence' => 0.87,
                'reply' => 'Hi Sam, we can help update the email on your account. For your security, please confirm the old email and the new address you’d like to use. We’ll guide you through verification before making a change.',
                'status' => 'triaged', 'featured' => false,
            ],
            [
                'subject' => 'Can I get a copy of last month’s receipt?',
                'body' => 'I need a receipt for my records, but I cannot find the download option in billing history.',
                'channel' => 'email', 'customer_name' => 'Alex Morgan', 'category' => null, 'priority' => null, 'confidence' => null,
                'reply' => null, 'status' => 'open', 'featured' => false,
            ],
            [
                'subject' => 'Order status for my weekend shipment',
                'body' => 'I placed an order on Saturday and have not received a shipping confirmation yet. Could you check the package status?',
                'channel' => 'phone', 'customer_name' => 'Quinn Davis', 'category' => null, 'priority' => null, 'confidence' => null,
                'reply' => null, 'status' => 'open', 'featured' => false,
            ],
            [
                'subject' => 'How do I invite a teammate?',
                'body' => 'I am setting up our workspace and cannot find where to invite another teammate. Is this available on our plan?',
                'channel' => 'chat', 'customer_name' => 'Parker Bell', 'category' => null, 'priority' => null, 'confidence' => null,
                'reply' => null, 'status' => 'open', 'featured' => false,
            ],
        ];

        foreach ($tickets as $ticket) {
            SupportTicket::create([
                'subject' => $ticket['subject'],
                'body' => $ticket['body'],
                'channel' => $ticket['channel'],
                'customer_name' => $ticket['customer_name'],
                'predicted_category' => $ticket['category'],
                'predicted_priority' => $ticket['priority'],
                'suggested_reply' => $ticket['reply'],
                'confidence' => $ticket['confidence'],
                'status' => $ticket['status'],
                'is_featured' => $ticket['featured'],
            ]);
        }
    }
}
