<?php

namespace App\Services\Llm;

class FakeLlmClient implements LlmClient
{
    public function complete(array $input, array $schema): LlmResponse
    {
        $text = mb_strtolower(($input['subject'] ?? '').' '.($input['body'] ?? ''));

        if (str_contains($text, 'chargeback')) {
            $category = 'billing';
            $priority = 'urgent';
            $rationale = 'A chargeback is a time-sensitive billing dispute.';
            $reply = 'Hi '.$input['customer_name'].', I’m sorry for the concern around this charge. I’ve flagged the dispute for our billing team to review urgently. We’ll follow up with the next steps as soon as we verify the transaction.';
        } elseif (str_contains($text, 'invoice') || str_contains($text, 'charge')) {
            $category = 'billing';
            $priority = 'high';
            $rationale = 'The customer is asking about an invoice or payment charge.';
            $reply = 'Hi '.$input['customer_name'].', thanks for reaching out. I can help review this billing question. Please share the invoice date or the last four digits of the payment method, and we’ll check the charge for you.';
        } elseif (str_contains($text, 'tracking') || str_contains($text, 'package')) {
            $category = 'shipping';
            $priority = 'medium';
            $rationale = 'The request is about a package or shipment tracking update.';
            $reply = 'Hi '.$input['customer_name'].', I’m happy to look into your delivery. Please send your order number and I’ll check the latest tracking update and expected arrival.';
        } elseif (str_contains($text, 'crash') || str_contains($text, 'bug')) {
            $category = 'product_bug';
            $priority = 'high';
            $rationale = 'The customer reports a crash or product defect that blocks normal use.';
            $reply = 'Hi '.$input['customer_name'].', I’m sorry the app is crashing. Our product team will investigate. Could you share which device and app version you’re using, plus the steps that trigger the issue?';
        } elseif (str_contains($text, 'password') || str_contains($text, 'login')) {
            $category = 'account';
            $priority = 'medium';
            $rationale = 'The customer needs help accessing or securing their account.';
            $reply = 'Hi '.$input['customer_name'].', I can help you get back into your account. Please try the password reset link on the sign-in page. If it does not arrive, let us know and we’ll help verify the account.';
        } elseif (str_contains($text, 'hours')) {
            $category = 'other';
            $priority = 'low';
            $rationale = 'This is a general information question with no urgent service impact.';
            $reply = 'Hi '.$input['customer_name'].', our Harbor Desk team is available Monday through Friday, 9:00 AM to 5:00 PM ET. We’re happy to help if you have any other questions.';
        } else {
            $category = 'other';
            $priority = 'medium';
            $rationale = 'The message does not match a more specific support category.';
            $reply = 'Hi '.$input['customer_name'].', thanks for contacting Harbor Desk. We’ve received your message and will review it shortly. If you can share any extra details, please reply here.';
        }

        return new LlmResponse(json_encode([
            'category' => $category,
            'priority' => $priority,
            'suggested_reply' => $reply,
            'confidence' => $category === 'other' ? 0.76 : 0.93,
            'rationale' => $rationale,
        ], JSON_THROW_ON_ERROR), 'harbor-rules-v1');
    }
}
