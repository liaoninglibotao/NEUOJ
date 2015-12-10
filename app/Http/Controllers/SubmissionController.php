<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Validator;
use Storage;
use App\Http\Controllers\AuthController;
use App\Problem;
use App\User;
use App\Submission;

class SubmissionController extends Controller
{

    public function submitAction(Request $request, $problem_id)
    {
        if($request->method() == "POST")
        {
            $langsufix = [
                "C" => "c",
                "Java" => "java",
                "C++11" => "cc",
                "C++" => "cpp",
            ];

            $vdtor = Validator::make($request->all(),[
                "code" => "required|min:50|max:50000",
            ]);
            var_dump($request->input());
            var_dump($request->session()->all());
            $uid = $request->session()->get('uid');
            $fileName = "";
            $submission = new Submission;
            if($vdtor->fails())
            {
                return Redirect::to($request->server('HTTP_REFERER'))
                    ->withErrors($vdtor);
            }
            $fileName = $uid."-".$problem_id."-".time().".".$langsufix[$request->input('lang')];
            echo $fileName;
            Storage::put("submissions/".$fileName, $request->input('code'));
            $submission->pid = $problem_id;
            $submission->uid = $uid;
            $submission->cid = 0;
            $submission->lang = $request->input('lang');
            $submission->result = "Pending";
            $submission->submit_time = date('Y-m-d-H:i:s');
            $submission->submit_file = $fileName;
            $submission->md5sum = md5($request->input('code'));
            $submission->judge_status = 0;
            $submission->save();
            var_dump($submission);
            return Redirect::to($request->server('HTTP_REFERER'));
        }
    }

    public function getSubmissionByID(Request $request, $run_id)
    {
        $data = [];
        $submissionObj = Submission::where('runid', $run_id)->first();
        $fileContent = Storage::get("submissions/".$submissionObj->submit_file);
        $fileContent = nl2br($fileContent, false);
        $data = $submissionObj;
        $data->code = $fileContent;

        return View::make('status.index', $data);
    }

    public function getSubmission(Request $request)
    {
        return Redirect::to('/status/p/1');
    }

    public function getSubmissionListByPageID(Request $request, $page_id)
    {
        $itemsPerPage = 10;
        $data = [];
        $data['submissions'] = NULL;
        $input = $request->all();
        $queryArr = [];

        foreach($input as $key => $val)
        {
            if($val == "All" || $val == "")
                continue;
            if($key != "username")
                $queryArr[$key] = $val;
            if($key == "username")
            {
                $userObj = User::where('username', $val)->first();
                if($userObj != NULL)
                {
                    $queryArr['uid'] = $userObj->uid;
                }
                else
                {
                    $queryArr['uid'] = "Th11EN412am#eN%o0neCanCreEte";
                }
            }
        }
        $submissionObj = Submission::where($queryArr);
        $submissionObj = $submissionObj->orderby('runid', 'asc')->get();
        for($count = 0, $i = ($page_id - 1) * $itemsPerPage; $count < $itemsPerPage && $i < $submissionObj->count(); $i++, $count++)
        {
            $data['submissions'][$count] = $submissionObj[$i];
            $tmpUserObj = User::where('uid', $submissionObj[$i]->uid)->first();
            $tmpProblemObj = Problem::where('problem_id', $submissionObj[$i]->pid)->first();
            $problemTitle = $tmpProblemObj['title'];
            $username = $tmpUserObj['username'];
            $data['submissions'][$count]->userName = $username;
            $data['submissions'][$count]->problemTitle = $problemTitle;
        }
        if($i >= $submissionObj->count())
        {
            $data['lastPage'] = true;
        }
        if($page_id == 1)
        {
            $data['firstPage'] = true;
        }
        $data['page_id'] = $page_id;

        return View::make('status.list', $data);
    }

}