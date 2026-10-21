<?php

namespace Ticket\Controllers;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Ticket\Models\TicketSetting;
use Illuminate\Http\Request;
use Ticket\Models\Ticket;
use Ticket\Models\TicketResponse;

class TicketController extends Controller
{
    public $path;

    public function __construct()
    {
        $this->middleware('auth');
        $this->path = 'Ticket::';
    }




    public function index(){
        hasPermissions('show_tickets');

        $title = transWord('Tickets');
        $pages = [
            [transWord('Tickets'),'show_tickets']
        ];

        $tickets = Ticket::latest()->get();

        return view($this->path.'index',compact('tickets','pages','title'));
    }


    public function responses($ticket){
        $title = transWord('Tickets Responses');
        $pages = [
            [transWord('Tickets Responses'),'show_tickets']
        ];
        $ticket = Ticket::where('id',$ticket)->first();
        $responses = TicketResponse::where('ticket_id',$ticket->id)->get();
        return view($this->path.'responses',compact('ticket','pages','title','responses'));
    }
    
    public function addResponse(Request $request,$ticket){
        $response = TicketResponse::create([
            'ticket_id' => $ticket,
            'response' => $request->response,
            'reply_at' =>Carbon::now(),
            'user' => 'admin'
        ]);
        return redirect()->back()->with('success' ,'');
    }


    public function response(Request $request){
        hasPermissions('create_tickets');
        $request->validate([
            'response' =>'required|string',
        ]);

        $faq= Ticket::create($request->all());


        return redirect()->route('show_faq_settings')->with('success' ,'');
    }


    public function close(Ticket $faq){
        hasPermissions('delete_tickets');

        $faq->delete();
        return redirect()->route('show_faq_settings')->with('success' ,'');
    }








}
