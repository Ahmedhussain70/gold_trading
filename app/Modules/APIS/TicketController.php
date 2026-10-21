<?php

namespace App\Modules\APIS;
use App\Http\Controllers\Controller;
use App\Http\Resources\FAQResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\TicketResource;
use App\Http\Resources\TicketResponseResource;
use Carbon\Carbon;
use FaqSection\Models\FaqSetting;
use Illuminate\Http\Request;
use Product\Models\Product;
use Ticket\Models\Ticket;
use Ticket\Models\TicketResponse;

class TicketController extends Controller
{
    public function tickets(){
        $tickets = Ticket::where('user_id',auth()->user()->id)->get();
        $data = TicketResource::collection($tickets);
        return response()->json([
            'success' => true,
            'message' => transWord("Tickets"),
            'result' => $data,
        ],200);
    }
    
    public function makeTicket(Request $request){
        $request->validate([
            'message' => 'required',
        ]);

        $ticket = Ticket::create([
            'user_id' => auth()->user()->id,
            'message' => $request->message,
            'send_at' =>Carbon::now(),
            'status' => 1
        ]);

        $data = new TicketResource($ticket);
        
        return response()->json([
            'success' => true,
            'message' => transWord("The Ticket is sent successfully"),
            'result' => $data,
        ],200);
    }
    
    public function responses($ticket_id){
        $tickets = TicketResponse::where('ticket_id',$ticket_id)->get();
        $data = TicketResponseResource::collection($tickets);
        return response()->json([
            'success' => true,
            'message' => transWord("Ticket Responses"),
            'result' => $data,
        ],200);
    }
    
    public function makeResponse(Request $request){
        $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'response' => 'required',
        ]);
        

        $ticket = Ticket::where('id',$request->ticket_id)->first();
        if ($ticket == null) {
            return response()->json([
                'success' => false,
                'message' => transWord("Ticket Not Found Or You Not Owned This Ticket"),
                'result' => null,
            ],200);
        }
        else if ($ticket->status == 0) {
            return response()->json([
                'success' => false,
                'message' => transWord("Ticket is closed you can not reply"),
                'result' => null,
            ],200);
        }
        else{
            $response = TicketResponse::create([
                'ticket_id' => $request->ticket_id,
                'response' => $request->response,
                'reply_at' =>Carbon::now(),
                'user' => 'you'
            ]);
    
            $data = new TicketResponseResource($response);
            
            return response()->json([
                'success' => true,
                'message' => transWord("The Response is sent successfully"),
                'result' => $data,
            ],200);
        }
    }

}
