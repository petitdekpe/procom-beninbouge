<?php

namespace App\Entity\Enum;

enum SubmissionType: string
{
    case TopicProposal = 'topic_proposal';
    case EventReport = 'event_report';
}
