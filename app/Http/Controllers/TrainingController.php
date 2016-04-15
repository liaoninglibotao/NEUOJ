<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

use App\Train;
use App\TrainProblem;
use Problem;
use App\Submission;
use App\User;
use App\Userinfo;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Controller;

class TrainingController extends Controller
{
    public function showTrainingDashboard(Request $request)
    {
        $data = [];
        $data['training'] = [];
        $trainingObj = Train::orderby('train_id', 'asc')->get();
        $data['training'] = $trainingObj;
        $trainNum = $trainingObj->count();
        $data['trainNum'] = $trainNum;
        return View::make('training.dashboard')->with($data);
    }

    public function addTraining(Request $request)
    {
        $data = [];
        $errMsg = new MessageBag;
        if($request->method() == "POST")
        {
            $this->validate($request, [
                'train_name' => 'required | unique:train_info',
                'problem_id' => 'required | exists:problems'
            ]);
            //check each chapter has at least one problem
            $input = $request->all();
            $checkChapterProblem = 0;
            for($i = 1; $i <= $input['train_chapter']; $i++)
            {
                $chapterProblem = 0;
                foreach($input['problem_chapter'] as $chapter)
                {
                    if($chapter == $i)
                        $chapterProblem++;
                }
                var_dump($chapterProblem);
                var_dump($i);
                if($chapterProblem == 0)
                {
                    $checkChapterProblem = 1;
                    break;
                }
            }
            if($checkChapterProblem)
            {
                $errMsg->add('err', 'Chapter must has at least one problem');
            }
            if(!$errMsg->isEmpty())
            {
                var_dump($errMsg);
                return Redirect::to('/dashboard/training/add')->withErrors($errMsg)->withInput($input);
            }
            $trainingObj = new Train;
            $trainingObj->train_name = $input['train_name'];
            $trainingObj->train_chapter = $input['train_chapter'];
            $trainingObj->description = " ";
            $trainingObj->train_type = 0;
            $trainingObj->auth_id = $request->session()->get('uid');
            $trainingObj->save();
            var_dump($trainingObj->train_id);
            for($i = 0; $i < count($input['problem_id']); $i++)
            {
                $trainingProblemObj = new TrainProblem;
                $trainingProblemObj->train_id = $trainingObj->train_id;
                $trainingProblemObj->problem_id = $input['problem_id'][$i];
                $trainingProblemObj->chapter_id = $input['problem_chapter'][$i];
                $trainingProblemObj->train_problem_id = count(TrainProblem::where('chapter_id', $input['problem_chapter'][$i])->get())+1;
                $trainingProblemObj->problem_title = $input['problem_name'][$i];
                $trainingProblemObj->problem_level = 0;
                $trainingProblemObj->save();
            }
            return Redirect::to('/dashboard/training/p/1');
        }
        return View::make("training.add");
    }

    public function deleteTraining(Request $request, $train_id)
    {
        Train::where('train_id', $train_id)->delete();
        TrainProblem::where('train_id', $train_id)->delete();
        return Redirect::to('/dashboard/training/p/1');
    }

    public function setTraining(Request $request)
    {

    }

    public function getTrainingList(Request $request)
    {
        $data = [];
        $data['training'] = [];
        $trainingObj = Train::orderby('train_id', 'asc')->get();
        $trainNum = $trainingObj->count();
        $data['training'] = $trainingObj;
        $data['trainNum'] = $trainNum;
        return View::make('training.list')->with($data);
    }

    public function getTrainingByID(Request $request, $train_id)
    {
        $data = [];
        $data['chapterac'] = [];
        $uid = $request->session()->get('uid');
        $trainingObj = Train::where('train_id', $train_id)->first();
        $data['training'] = $trainingObj;
        //chapter_in is to find which chapter is user in
        $chapter_in = 1 ;
        for($i = 1; $i <= $trainingObj->train_chapter; $i++)
        {
            $trainingProblemObj = TrainProblem::where([
                'train_id' => $train_id,
                'chapter_id' => $i
            ])->get();
            //checkChapterAc is to find whether user has finished this chapter
            $checkChapterAc = 0;
            $problem_num = 0;
            foreach($trainingProblemObj as $trainingProblem)
            {
                $submissionObj = Submission::where([
                    'uid' => $uid,
                    'pid' => $trainingProblem->problem_id,
                    'result' => 'Accepted'
                ])->orderby('runid','asc')->first();
                if(!isset($submissionObj))
                    $checkChapterAc = 0;
                else
                    $checkChapterAc = 1;
                $data['chapter'][$i][$problem_num] = $trainingProblem->problem;
                $data['chapter'][$i][$problem_num++]['ac'] = $checkChapterAc;
            }
            if($checkChapterAc == 1)
                $chapter_in = $i+1;
        }
        $data['chapter_in'] = $chapter_in;
        return View::make('training.index')->with($data);
    }
}
