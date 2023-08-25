<?php

enum OrderStatus: string {
    case Open   = 'open';
    case Served = 'served';
    case Paid   = 'paid';
}

enum PoStatus: string {
    case Open     = 'open';
    case Received = 'received';
}
